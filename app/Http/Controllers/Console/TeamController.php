<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Console\ConsoleSampleData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PROVISOIRE : l'equipe editeur (README ecran 34), rendue sur le jeu d'exemple, en attendant les
 * comptes editeur distincts.
 */
class TeamController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/team', [
            'isSample' => true,
            ...ConsoleSampleData::team(),
        ]);
    }
}
