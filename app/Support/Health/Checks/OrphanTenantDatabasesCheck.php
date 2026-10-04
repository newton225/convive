<?php

namespace App\Support\Health\Checks;

use App\Support\TenantDatabaseFiles;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Un fichier de base sans organisation (`TenantDatabaseFiles::orphans()`) : il bloque toute creation
 * d'organisation des que SQLite reprend son numero. A effacer a la main apres verification, jamais
 * automatiquement : le controle le signale, l'alerte part par courriel.
 */
class OrphanTenantDatabasesCheck extends Check
{
    public function run(): Result
    {
        $orphans = TenantDatabaseFiles::orphans();

        $result = Result::make()
            ->meta(['files' => $orphans])
            ->shortSummary((string) count($orphans));

        return $orphans === []
            ? $result->ok()
            : $result->failed('Orphan tenant database file(s): '.implode(', ', $orphans).'.');
    }
}
