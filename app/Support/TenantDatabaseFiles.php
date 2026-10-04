<?php

namespace App\Support;

use App\Models\Tenant;

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

        foreach (scandir(ScopedSqliteDatabaseManager::directory()) ?: [] as $file) {
            if (preg_match($pattern, $file) === 1 && ! in_array($file, $known, true)) {
                $orphans[] = $file;
            }
        }

        sort($orphans);

        return $orphans;
    }
}
