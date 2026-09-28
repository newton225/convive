<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Console\ConsoleSampleData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PROVISOIRE : le journal central (README ecran 33), rendu sur le jeu d'exemple.
 */
class AuditLogController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/audit', [
            'isSample' => true,
            'entries' => ConsoleSampleData::auditEntries(),
        ]);
    }
}
