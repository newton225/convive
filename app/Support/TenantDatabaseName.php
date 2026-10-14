<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Le nom du fichier de base d'une nouvelle organisation (decision du proprietaire du projet,
 * 2026-10-04) : `tenant_<ULID>.sqlite`, tire a la creation puis garde pour toujours sur
 * l'organisation (`tenancy_db_name`). Plus le numero de l'organisation : SQLite peut redonner le
 * numero d'une organisation disparue, et la nouvelle tombait sur un fichier deja present (incident du
 * meme jour).
 *
 * Un ULID porte la date de creation a la milliseconde puis 80 bits tires par le generateur aleatoire
 * securise : un doublon est hors d'atteinte. Il est quand meme verifie, contre les fichiers presents et
 * contre les noms enregistres (index unique en base) : un nom deja pris est tire de nouveau, jamais
 * reutilise, et aucun fichier existant n'est touche.
 */
final class TenantDatabaseName
{
    public const Prefix = 'tenant_';

    public static function generate(): string
    {
        do {
            $name = self::Prefix.Str::lower((string) Str::ulid()).config('tenancy.database.suffix');
        } while (self::taken($name));

        return $name;
    }

    private static function taken(string $name): bool
    {
        return self::existsOnServer($name)
            || Tenant::withTrashed()->where('tenancy_db_name', $name)->exists();
    }

    /**
     * Un fichier present en SQLite, une base presente sur le serveur en PostgreSQL.
     */
    private static function existsOnServer(string $name): bool
    {
        $central = DB::connection(config('tenancy.database.central_connection'));

        if ($central->getDriverName() === 'pgsql') {
            return $central->table('pg_database')->where('datname', $name)->exists();
        }

        return file_exists(ScopedSqliteDatabaseManager::directory().DIRECTORY_SEPARATOR.$name);
    }
}
