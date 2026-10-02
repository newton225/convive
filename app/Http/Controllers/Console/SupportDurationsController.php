<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\UpdateSupportDurations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdateSupportDurationsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Le reglage des durees d'un acces de support, depuis l'ecran Securite de la console.
 */
class SupportDurationsController extends Controller
{
    public function __invoke(UpdateSupportDurationsRequest $request, UpdateSupportDurations $update): RedirectResponse
    {
        $update->handle($request->durations(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.security.support_durations.flash')]);

        return to_route('console.security');
    }
}
