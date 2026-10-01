<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Backup\BackupStatus;
use App\Support\Console\TenantDatabaseHealth;
use App\Support\Health\HealthChecks;
use App\Support\Health\QueueOverview;
use App\Support\Health\ScheduledTasks;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La sante technique (README ecran 31), lue sur ce qui existe reellement : les controles de
 * `spatie/laravel-health` rejoues a l'affichage, le releve des taches planifiees, les files et
 * leurs travaux en echec, les bases des organisations et les sauvegardes.
 */
class HealthController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/health', [
            'isSample' => false,
            'checks' => HealthChecks::fresh(),
            'tasks' => ScheduledTasks::overview(),
            'queue' => [
                'pending' => QueueOverview::pending(),
                'failedCount' => QueueOverview::failedCount(),
                'failed' => QueueOverview::failed(),
            ],
            'databases' => TenantDatabaseHealth::issues(),
            'backup' => BackupStatus::current(),
        ]);
    }
}
