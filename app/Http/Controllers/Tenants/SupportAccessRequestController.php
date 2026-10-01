<?php

namespace App\Http\Controllers\Tenants;

use App\Actions\Tenants\ManageSupportAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\RequestSupportAccessRequest;
use App\Models\SupportAccessRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * La demande d'aide (README ecran 25 et section 3) : quand personne de l'equipe Convive n'est
 * visible, un Proprietaire previent l'equipe qu'il veut lui ouvrir son espace. La demande n'ouvre
 * aucun acces.
 */
class SupportAccessRequestController extends Controller
{
    /**
     * Send the request to the Convive team.
     */
    public function store(RequestSupportAccessRequest $request, Tenant $tenant, ManageSupportAccess $manage): RedirectResponse
    {
        $manage->request($tenant, $request->user(), $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('support_access.flash.requested')]);

        return to_route('tenants.support-access.show', $tenant);
    }

    /**
     * Withdraw the given request.
     */
    public function destroy(Request $request, Tenant $tenant, SupportAccessRequest $supportRequest, ManageSupportAccess $manage): RedirectResponse
    {
        Gate::authorize('manageSupportAccess', $tenant);

        // Table centrale : la demande d'une autre organisation n'existe pas pour celle-ci.
        abort_if($supportRequest->tenant_id !== $tenant->id, 404);

        $manage->cancelRequest($supportRequest, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('support_access.flash.request_cancelled')]);

        return to_route('tenants.support-access.show', $tenant);
    }
}
