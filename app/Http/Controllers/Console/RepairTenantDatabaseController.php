<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\RepairTenantDatabase;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

/**
 * « Rejouer les migrations » (README ecran 31) : repare la base d'une organisation signalee par
 * l'ecran de sante technique. La zone `health` de la route la reserve aux Fondateurs.
 */
class RepairTenantDatabaseController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, RepairTenantDatabase $repair): RedirectResponse
    {
        try {
            $repair->handle($tenant, $request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('console.health.flash.migrated', ['organisation' => $tenant->name])]);
        } catch (Throwable $exception) {
            // Le detail technique va au journal, pas a l'ecran.
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('console.health.flash.migration_failed', ['organisation' => $tenant->name])]);
        }

        return to_route('console.health');
    }
}
