<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Console\ConsoleSampleData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PROVISOIRE : la sante technique (README ecran 31), rendue sur le jeu d'exemple. Le serveur
 * s'appuiera sur `spatie/laravel-health` (CLAUDE.md, table Spatie).
 */
class HealthController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/health', [
            'isSample' => true,
            ...ConsoleSampleData::health(),
        ]);
    }
}
