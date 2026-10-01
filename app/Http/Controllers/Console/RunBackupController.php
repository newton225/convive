<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\RunBackup;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

/**
 * « Sauvegarder maintenant » (README ecran 31). La zone `health` de la route la reserve aux
 * Fondateurs.
 */
class RunBackupController extends Controller
{
    public function __invoke(Request $request, RunBackup $backup): RedirectResponse
    {
        try {
            $backup->handle($request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('console.health.flash.backed_up')]);
        } catch (LockTimeoutException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('console.health.flash.backup_running')]);
        } catch (Throwable $exception) {
            // Le detail technique va au journal, pas a l'ecran.
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('console.health.flash.backup_failed')]);
        }

        return to_route('console.health');
    }
}
