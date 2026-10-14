<?php

namespace App\Support\Backup;

use App\Models\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Copie coherente de chaque base avant l'archivage : la base centrale, puis celle de chaque
 * organisation (CLAUDE.md, « Multi-locataire » : une base par locataire).
 *
 * Compromis avec le mecanisme du paquet, qui sauvegarde lui-meme les bases : son export SQLite
 * appelle le programme `sqlite3`, absent des serveurs ou seul PHP est installe, et il ne connait
 * que des connexions declarees d'avance, pas une base par organisation. `VACUUM INTO` passe par la
 * connexion deja ouverte et produit un fichier complet et coherent, y compris pendant qu'une
 * ecriture est en cours : copier le fichier tel quel pourrait saisir une base a moitie ecrite.
 *
 * Sur PostgreSQL, la meme classe lance `pg_dump` base par base (`<nom>.dump`, format personnalise) :
 * l'export du paquet ne connait pas non plus une base par organisation. Un autre moteur est refuse,
 * plutot que de produire une sauvegarde sans base.
 */
class DatabaseSnapshots
{
    /** Une base volumineuse peut prendre du temps : le delai de `pg_dump` est large. */
    private const DumpTimeoutSeconds = 1800;

    public static function directory(): string
    {
        // Le meme chemin, au separateur pres, que `backup.source.files.include`.
        return storage_path('app').DIRECTORY_SEPARATOR.'backup-databases';
    }

    /**
     * Snapshot the central database and every organisation's one. Returns how many were written.
     *
     * Une organisation dont la base manque est ignoree ici : l'ecran de sante technique la
     * signale deja, et une base absente ne doit pas empecher de sauvegarder toutes les autres.
     */
    public static function take(): int
    {
        // Calcule avant toute bascule : `storage_path()` change de racine sous une tenancy.
        $directory = self::directory();

        self::clear();
        File::ensureDirectoryExists($directory);

        self::snapshot(DB::connection('central'), $directory, 'central.sqlite');
        $count = 1;

        // Avec la corbeille : une organisation supprimee reste restaurable trente jours, sa base se
        // sauvegarde donc jusqu'a son effacement.
        Tenant::withTrashed()->each(function (Tenant $tenant) use ($directory, &$count) {
            $database = $tenant->database();

            if (! $database->manager()->databaseExists($database->getName())) {
                return;
            }

            $tenant->run(fn () => self::snapshot(
                DB::connection(),
                $directory,
                // Sous le nom reel de la base : la restauration la remet telle quelle
                // (`tenant_<ULID>.sqlite` ou `tenant_<ULID>.dump` pour les organisations ouvertes
                // depuis le 2026-10-04).
                $database->getName(),
            ));

            $count++;
        });

        return $count;
    }

    /**
     * Remove the snapshots once archived : des copies de toutes les bases ne trainent pas en clair
     * a cote de l'application.
     */
    public static function clear(): void
    {
        File::deleteDirectory(self::directory());
    }

    /**
     * Copie une base dans le dossier des copies, sous `$name` : un fichier SQLite par `VACUUM INTO`, un
     * export `pg_dump` (`<nom>.dump`) en PostgreSQL.
     */
    private static function snapshot(Connection $connection, string $directory, string $name): void
    {
        match ($connection->getDriverName()) {
            'sqlite' => self::snapshotSqlite($connection, $directory.DIRECTORY_SEPARATOR.$name),
            'pgsql' => self::snapshotPostgres($connection, $directory.DIRECTORY_SEPARATOR.self::dumpName($name)),
            default => throw new RuntimeException("La sauvegarde ne sait lire qu'une base SQLite ou PostgreSQL, pas « {$connection->getDriverName()} »."),
        };
    }

    private static function snapshotSqlite(Connection $connection, string $path): void
    {
        $pdo = $connection->getPdo();

        // `VACUUM INTO` n'accepte pas de parametre lie : le chemin est mis entre guillemets par le
        // pilote. Il vient du code, jamais d'une saisie.
        $pdo->exec('VACUUM INTO '.$pdo->quote($path));
    }

    /**
     * Export au format personnalise de `pg_dump` (restauration par `pg_restore`, y compris d'une seule
     * table). Sans proprietaire ni droits : la restauration se fait avec le role du serveur cible.
     *
     * Le mot de passe passe par l'environnement du processus (`PGPASSWORD`), jamais par la ligne de
     * commande, ou n'importe quel utilisateur du serveur le lirait. Les arguments sont une liste, sans
     * interpreteur de commandes.
     */
    private static function snapshotPostgres(Connection $connection, string $path): void
    {
        $config = $connection->getConfig();

        $result = Process::timeout(self::DumpTimeoutSeconds)
            ->env(['PGPASSWORD' => (string) ($config['password'] ?? '')])
            ->run([
                (string) config('backup.pg_dump_binary'),
                '--format=custom',
                '--no-owner',
                '--no-acl',
                '--host='.$config['host'],
                '--port='.$config['port'],
                '--username='.$config['username'],
                '--file='.$path,
                (string) $config['database'],
            ]);

        if ($result->failed()) {
            throw new RuntimeException("pg_dump a echoue pour « {$config['database']} » : ".trim($result->errorOutput()));
        }
    }

    /**
     * Le nom d'une copie PostgreSQL : celui de la base, sans l'extension `.sqlite` des anciennes.
     */
    private static function dumpName(string $name): string
    {
        return Str::beforeLast($name, '.sqlite').'.dump';
    }
}
