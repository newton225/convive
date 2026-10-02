<?php

namespace App\Support\Backup;

use App\Models\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
 * Le jour du passage a PostgreSQL, cette classe laisse la place a l'export du paquet
 * (`backup.source.databases`) : elle refuse d'ici la tout autre moteur, plutot que de produire une
 * sauvegarde sans base.
 */
class DatabaseSnapshots
{
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

        self::snapshot(DB::connection('central'), $directory.DIRECTORY_SEPARATOR.'central.sqlite');
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
                $directory.DIRECTORY_SEPARATOR."tenant{$tenant->id}.sqlite",
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

    private static function snapshot(Connection $connection, string $path): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException("La sauvegarde par copie ne sait lire qu'une base SQLite, pas « {$connection->getDriverName()} ».");
        }

        $pdo = $connection->getPdo();

        // `VACUUM INTO` n'accepte pas de parametre lie : le chemin est mis entre guillemets par le
        // pilote. Il vient du code, jamais d'une saisie.
        $pdo->exec('VACUUM INTO '.$pdo->quote($path));
    }
}
