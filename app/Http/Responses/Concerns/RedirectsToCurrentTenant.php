<?php

namespace App\Http\Responses\Concerns;

use App\Models\Tenant;
use App\Support\PendingTenantInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTenant
{
    protected function redirectPathForCurrentTenant(Request $request, string $redirect): string
    {
        $tenant = $this->currentTenant($request);

        // Une invitation suivie depuis son courriel, ou un compte sans organisation : l'accueil des
        // invitations, ou la personne choisit (TODO du 2026-10-07). Jamais une erreur a la place :
        // un 403 brut a la connexion laissait une page vide.
        if ($tenant === null || PendingTenantInvitation::current($request) !== null) {
            return route('invitations.index', absolute: false);
        }

        URL::defaults(['current_tenant' => $tenant->slug]);

        return "/{$tenant->slug}{$redirect}";
    }

    protected function currentTenant(Request $request): ?Tenant
    {
        $user = $request->user();

        abort_if(! $user, 403);

        return $user->currentTenant ?? $user->personalTenant() ?? $user->tenants()->first();
    }
}
