<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\ManageFailedJobs;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Les travaux de file en echec (README ecran 31) : relancer ou ecarter. La zone `health` de la
 * route reserve ces gestes aux Fondateurs.
 */
class FailedJobController extends Controller
{
    /**
     * Put the failed job back on its queue.
     */
    public function retry(Request $request, string $job, ManageFailedJobs $manage): RedirectResponse
    {
        abort_unless($manage->retry($job, $request->user()), 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.health.flash.job_retried')]);

        return to_route('console.health');
    }

    /**
     * Drop the failed job for good.
     */
    public function destroy(Request $request, string $job, ManageFailedJobs $manage): RedirectResponse
    {
        abort_unless($manage->forget($job, $request->user()), 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.health.flash.job_forgotten')]);

        return to_route('console.health');
    }
}
