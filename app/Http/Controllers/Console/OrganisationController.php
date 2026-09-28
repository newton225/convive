<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Console\ConsoleSampleData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PROVISOIRE : les organisations clientes vues de la console (README ecrans 27 et 28), rendues sur
 * le jeu d'exemple. Remplace, pas complete, quand la table centrale des compteurs (`TenantUsage`)
 * et les actions de l'editeur arrivent.
 */
class OrganisationController extends Controller
{
    /**
     * Display the list of client organisations.
     */
    public function index(): Response
    {
        return Inertia::render('console/organisations', [
            'isSample' => true,
            'organisations' => ConsoleSampleData::organisations(),
        ]);
    }

    /**
     * Display one client organisation : metadata only, never its content (README section 3).
     */
    public function show(string $organisation): Response
    {
        $details = ConsoleSampleData::organisation($organisation);

        abort_if($details === null, 404);

        return Inertia::render('console/organisation', [
            'isSample' => true,
            'organisation' => $details,
        ]);
    }
}
