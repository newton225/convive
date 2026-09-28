<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Console\ConsoleSampleData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PROVISOIRE : la moderation de la vitrine (README ecran 32), rendue sur le jeu d'exemple.
 */
class ShowcaseController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/showcase', [
            'isSample' => true,
            'announcements' => ConsoleSampleData::announcements(),
            'withdrawn' => ConsoleSampleData::withdrawnAnnouncements(),
        ]);
    }
}
