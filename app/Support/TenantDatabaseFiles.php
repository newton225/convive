<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Les fichiers de base des organisations, et ceux qui n'appartiennent a aucune.
 *
 * Deux formes de nom : `tenant<numero>.sqlite` pour les organisations ouvertes avant le 2026-10-04,
 * `tenant_<ULID>.sqlite` ensuite (`TenantDatabaseName`). Un fichier appartient a une organisation
 * quand son nom est celui qu'elle a enregistre (`tenancy_db_name`) ; une organisation en corbeille
 * garde sa base, elle reste recuperable trente jours.
 *
 * Un orphelin pouvait bloquer toute creation d'organisation, SQLite reprenant le numero de la
 * derniere organisation disparue (incident du 2026-10-04).
 */
final class TenantDatabaseFiles
{
    /**
     * Get the names of the database files that belong to no organisation, trash included.
     *
     * @return array<int, string>
     */
    public static function orphans(): array
    {
        $prefix = preg_quote((string) config('tenancy.database.prefix'), '/');
        $suffix = preg_quote((string) config('tenancy.database.suffix'), '/');
        $pattern = '/^'.$prefix.'(\d+|_[0-9a-z]{26})'.$suffix.'$/';

        $known = Tenant::withTrashed()->get()
            // Sans nom enregistre (cas que la migration du 2026-10-04 a comble), l'ancienne regle :
            // jamais le generateur, qui tirerait un nom au hasard.
            ->map(fn (Tenant $tenant) => $tenant->tenancy_db_name
                ?? config('tenancy.database.prefix').$tenant->id.config('tenancy.database.suffix'))
            ->all();

        $orphans = [];

        foreach (self::existing() as $file) {
            if (preg_match($pattern, $file) === 1 && ! in_array($file, $known, true)) {
                $orphans[] = $file;
            }
        }

        sort($orphans);

        return $orphans;
    }

    /**
     * Les noms presents sur le serveur : les fichiers du dossier des bases en SQLite, les bases du
     * serveur en PostgreSQL. Sert de liste candidate : `orphans()` ne garde que les noms d'organisation.
     *
     * @return array<int, string>
     */
    private static function existing(): array
    {
        if (self::onPostgres()) {
            return self::central()->table('pg_database')->pluck('datname')->all();
        }

        return scandir(ScopedSqliteDatabaseManager::directory()) ?: [];
    }

    /**
     * La taille en octets d'un orphelin, ou null s'il ne se lit pas.
     */
    public static function size(string $name): ?int
    {
        if (self::onPostgres()) {
            $size = self::central()->selectOne('select pg_database_size(?) as size', [$name])->size ?? null;

            return $size === null ? null : (int) $size;
        }

        $size = @filesize(ScopedSqliteDatabaseManager::directory().DIRECTORY_SEPARATOR.$name);

        return $size === false ? null : $size;
    }

    /**
     * Efface la base d'un orphelin. Le nom doit deja avoir ete verifie contre `orphans()` : il est
     * quand meme cite comme identifiant, jamais colle tel quel dans la requete.
     */
    public static function drop(string $name): bool
    {
        if (self::onPostgres()) {
            // Refuse (sans FORCE) tant qu'une connexion y est ouverte : un orphelin n'en a aucune.
            try {
                return self::central()->statement('drop database '.self::central()->getQueryGrammar()->wrap($name));
            } catch (QueryException) {
                return false;
            }
        }

        return @unlink(ScopedSqliteDatabaseManager::directory().DIRECTORY_SEPARATOR.$name);
    }

    private static function onPostgres(): bool
    {
        return self::central()->getDriverName() === 'pgsql';
    }

    private static function central(): Connection
    {
        return DB::connection(config('tenancy.database.central_connection'));
    }
}
