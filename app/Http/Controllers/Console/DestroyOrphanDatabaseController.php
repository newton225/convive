<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\DeleteOrphanDatabase;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * « Supprimer » a cote d'une base orpheline, sur l'ecran de sante technique. La zone `health` de la
 * route la reserve aux Fondateurs ; un nom qui n'est pas celui d'un orphelin recoit 404.
 */
class DestroyOrphanDatabaseController extends Controller
{
    public function __invoke(Request $request, string $file, DeleteOrphanDatabase $delete): RedirectResponse
    {
        abort_unless($delete->handle($file, $request->user()), 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.health.flash.orphan_deleted', ['file' => $file])]);

        return to_route('console.health');
    }
}
