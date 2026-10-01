<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Backup\BackupStatus;
use App\Support\Console\TenantDatabaseHealth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La sante technique (README ecran 31) : les bases des organisations (`TenantDatabaseHealth`) et
 * les sauvegardes (`BackupStatus`), lues sur ce qui existe reellement. Le suivi des taches
 * planifiees et des files reste a construire avec `spatie/laravel-health` (CLAUDE.md, table
 * Spatie) : rien ne s'affiche a leur sujet d'ici la, plutot qu'un jeu d'exemple.
 */
class HealthController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/health', [
            'isSample' => false,
            'databases' => TenantDatabaseHealth::issues(),
            'backup' => BackupStatus::current(),
        ]);
    }
}
