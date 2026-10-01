<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Arrete les operations d'une organisation suspendue (README section 3) : pour impaye a J+10, ou
 * a la main par l'editeur. Le back-office des evenements renvoie vers l'ecran d'abonnement, ou
 * l'equipe peut regler ; le message dit laquelle des deux suspensions s'applique.
 *
 * Les reglages de l'organisation (equipe, profils, abonnement) restent accessibles : c'est par la
 * qu'on regularise. Vient apres `EnsureTenantMembership` : un locataire tiers a deja recu 404, il
 * n'apprend rien de l'etat de facturation de l'organisation.
 */
class EnsureTenantIsNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        // La tenancy est deja initialisee par `EnsureTenantMembership`, prepose a tout le reste.
        $tenant = Tenant::current();

        if ($tenant !== null && $tenant->isSuspended()) {
            return redirect()->route('tenants.billing.show', $tenant)
                ->withErrors(['billing' => __($tenant->isSuspendedByEditor()
                    ? 'billing.errors.suspended_by_editor'
                    : 'billing.errors.suspended')]);
        }

        return $next($request);
    }
}
