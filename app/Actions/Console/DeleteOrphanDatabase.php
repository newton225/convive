<?php

namespace App\Actions\Console;

use App\Models\User;
use App\Support\Console\ConsoleJournal;
use App\Support\ScopedSqliteDatabaseManager;
use App\Support\TenantDatabaseFiles;

/**
 * Supprime une base qui n'appartient a aucune organisation (decision du proprietaire du projet,
 * 2026-10-04), depuis l'ecran de sante technique plutot qu'a la main sur le serveur.
 *
 * Le nom doit figurer dans la liste des orphelins recalculee a l'instant : jamais la base d'une
 * organisation, meme en corbeille, et jamais un chemin hors du dossier des bases.
 */
class DeleteOrphanDatabase
{
    public function handle(string $file, User $actor): bool
    {
        if (! in_array($file, TenantDatabaseFiles::orphans(), true)) {
            return false;
        }

        $path = ScopedSqliteDatabaseManager::directory().DIRECTORY_SEPARATOR.$file;
        $size = filesize($path);

        if (! @unlink($path)) {
            return false;
        }

        ConsoleJournal::record('orphan_database_deleted', $actor, null, [
            'file' => $file,
            'size' => $size === false ? null : $size,
        ]);

        return true;
    }
}
