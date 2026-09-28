<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Console\ConsoleSampleData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PROVISOIRE : le recouvrement (README ecran 29), rendu sur le jeu d'exemple.
 */
class RecoveryController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/recovery', [
            'isSample' => true,
            'unpaid' => ConsoleSampleData::unpaid(),
            'failedPayments' => ConsoleSampleData::failedPayments(),
        ]);
    }
}
