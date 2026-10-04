<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Auth\TwoFactorDetour;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme le back-office d'une organisation aux membres dont le profil exige la double
 * authentification tant qu'ils ne l'ont pas activee.
 *
 * Ce controle vient apres EnsureTenantMembership : l'appartenance est deja verifiee, la
 * tenancy deja initialisee, et un locataire tiers a deja recu 404. Les routes qui permettent
 * de sortir d'une organisation ou d'en changer restent hors de sa portee, sinon un membre
 * serait enferme.
 */
class EnsureTwoFactorForProfile
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('convive.two_factor.enforced')) {
            return $next($request);
        }

        [$user, $tenant] = [$request->user(), Tenant::current()];

        if (! $user || ! $tenant || $user->hasEnabledTwoFactorAuthentication()) {
            return $next($request);
        }

        if (! $user->tenantProfile($tenant)?->demandsTwoFactor()) {
            return $next($request);
        }

        // L'ecran de securite dit pourquoi on y arrive, et ramene ici une fois la double
        // authentification activee.
        if ($request->isMethod('GET')) {
            TwoFactorDetour::remember($request, TwoFactorDetour::Profile, $request->getRequestUri());
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('account.two_factor.required_by_profile'),
        ]);

        return redirect()->route('security.edit');
    }
}
