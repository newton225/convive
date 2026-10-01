<?php

namespace App\Http\Controllers\Console;

use App\Actions\Tenants\ManageSupportAccess;
use App\Http\Controllers\Controller;
use App\Models\SupportAccessGrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * « J'ai termine » (README section 3) : la personne de l'equipe Convive ferme elle-meme l'acces de
 * support qu'une organisation lui a ouvert, avec ce qu'elle a constate. L'acces ne reste pas
 * ouvert pour rien jusqu'a son echeance, et l'organisation lit la conclusion dans son historique.
 */
class FinishSupportAccessController extends Controller
{
    public function __invoke(Request $request, SupportAccessGrant $grant, ManageSupportAccess $manage): RedirectResponse
    {
        // Seule la personne nommee ferme son acces, et seulement tant qu'il est en cours : pour
        // tout autre compte, cet acces n'existe pas.
        abort_if($grant->operator_id !== $request->user()->id || ! $grant->isActive(), 404);

        $validated = $request->validate(
            ['note' => ['required', 'string', 'min:10', 'max:1000']],
            ['note.required' => __('support_access.errors.note'), 'note.min' => __('support_access.errors.note')],
        );

        $manage->finish($grant, $validated['note']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('support_access.flash.finished', ['organisation' => $grant->tenant->name]),
        ]);

        return to_route('console.organisations.index');
    }
}
