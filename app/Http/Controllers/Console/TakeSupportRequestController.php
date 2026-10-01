<?php

namespace App\Http\Controllers\Console;

use App\Actions\Tenants\ManageSupportAccess;
use App\Http\Controllers\Controller;
use App\Models\SupportAccessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * « Prendre en charge » une demande d'aide (README section 3) : la personne de l'equipe Convive
 * devient visible de cette organisation seulement, dont les Proprietaires sont prevenus. Aucun
 * acces n'est ouvert : c'est l'organisation qui l'ouvre. La zone `support` de la route reserve le
 * geste aux profils qui peuvent recevoir un acces.
 */
class TakeSupportRequestController extends Controller
{
    public function __invoke(Request $request, SupportAccessRequest $supportRequest, ManageSupportAccess $manage): RedirectResponse
    {
        // Une demande annulee, ou deja suivie d'un acces, n'existe plus pour la console.
        abort_if(! $supportRequest->isPending(), 404);

        $manage->take($supportRequest, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('console.support_requests.flash.taken', ['organisation' => $supportRequest->tenant->name]),
        ]);

        return to_route('console.organisations.index');
    }
}
