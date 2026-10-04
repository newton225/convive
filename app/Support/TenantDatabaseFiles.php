<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Les fichiers de base des organisations (`tenant{id}.sqlite`), et ceux qui n'appartiennent a aucune.
 *
 * Un orphelin bloque toute creation d'organisation : SQLite reprend le numero de la derniere
 * organisation supprimee de la table, et la nouvelle tombe sur un fichier deja present (incident du
 * 2026-10-04). Une organisation en corbeille garde sa base : elle reste recuperable trente jours.
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
        $prefix = (string) config('tenancy.database.prefix');
        $suffix = (string) config('tenancy.database.suffix');
        $pattern = '/^'.preg_quote($prefix, '/').'(\d+)'.preg_quote($suffix, '/').'$/';

        $known = Tenant::withTrashed()->pluck('id')->map(fn ($id) => (string) $id)->all();
        $orphans = [];

        foreach (scandir(ScopedSqliteDatabaseManager::directory()) ?: [] as $file) {
            if (preg_match($pattern, $file, $match) === 1 && ! in_array($match[1], $known, true)) {
                $orphans[] = $file;
            }
        }

        sort($orphans);

        return $orphans;
    }
}
