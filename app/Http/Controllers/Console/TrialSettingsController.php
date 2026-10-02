<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\UpdateTrialSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdateTrialSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Le reglage de la periode d'essai, depuis l'ecran des plans (README ecran 30).
 */
class TrialSettingsController extends Controller
{
    public function __invoke(UpdateTrialSettingsRequest $request, UpdateTrialSettings $update): RedirectResponse
    {
        $update->handle($request->settings(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.trial.flash.updated')]);

        return to_route('console.plans');
    }
}
