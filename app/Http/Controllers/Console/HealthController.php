<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Console\ConsoleSampleData;
use App\Support\Console\TenantDatabaseHealth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La sante technique (README ecran 31). Les bases des organisations sont reelles
 * (`TenantDatabaseHealth`). PROVISOIRE pour le reste : taches planifiees, files et sauvegardes
 * viennent encore du jeu d'exemple, en attendant `spatie/laravel-health` (CLAUDE.md, table Spatie).
 */
class HealthController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/health', [
            'isSample' => true,
            ...ConsoleSampleData::health(),
            'databases' => TenantDatabaseHealth::issues(),
        ]);
    }
}
