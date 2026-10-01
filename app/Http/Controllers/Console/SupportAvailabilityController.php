<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * La visibilite d'une personne de l'equipe Convive dans l'acces du support (README section 3) :
 * chacun decide pour soi d'apparaitre dans la liste proposee aux organisations. Masquee, elle
 * n'y figure pas et aucun acces ne peut lui etre ouvert ; ceux deja ouverts courent jusqu'a leur
 * terme. La route est sous `EnsureConsoleOperator` : seul un compte de l'equipe l'atteint.
 */
class SupportAvailabilityController extends Controller
{
    /**
     * Update whether the signed-in member of the Convive team is offered to organisations.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(['available' => ['required', 'boolean']]);

        $request->user()->forceFill(['support_available' => (bool) $validated['available']])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($validated['available'] ? 'console.support_grants.flash.available' : 'console.support_grants.flash.unavailable'),
        ]);

        return to_route('console.organisations.index');
    }
}
