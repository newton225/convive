<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\UpdateSingleSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdateSingleSessionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * L'interrupteur « une seule session par compte », depuis l'ecran Securite de la console.
 */
class SingleSessionController extends Controller
{
    public function __invoke(UpdateSingleSessionRequest $request, UpdateSingleSession $update): RedirectResponse
    {
        $enabled = $request->boolean('single_session');

        $update->handle($enabled, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($enabled ? 'console.security.single_session.flash_on' : 'console.security.single_session.flash_off'),
        ]);

        return to_route('console.security');
    }
}
