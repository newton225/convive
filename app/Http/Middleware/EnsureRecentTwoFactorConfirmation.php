<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Auth\TwoFactorDetour;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un code TOTP rejoue juste avant une action sensible (comptes de versement,
 * SECURITY.md C1), sur le meme principe que `Illuminate\Auth\Middleware\RequirePassword` pour
 * le mot de passe, mais pour le second facteur : les deux se completent, aucun ne remplace
 * l'autre.
 *
 * Un membre qui n'a pas active la double authentification ne peut pas rejouer un code qui
 * n'existe pas : il est renvoye vers l'ecran de securite plutot que bloque sans explication.
 */
class EnsureRecentTwoFactorConfirmation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Depuis une page de l'application, on y reste : partir vers un autre ecran ferait perdre
        // ce qui vient d'etre saisi. La page demande le code dans une fenetre, puis on renvoie.
        $stayOnPage = $request->hasHeader('X-Inertia') && ! $request->isMethod('GET');

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            if ($stayOnPage) {
                return back()->withErrors(['two_factor_setup' => __('account.two_factor_reconfirm.setup_required')]);
            }

            if (($tenant = Tenant::current()) !== null) {
                TwoFactorDetour::remember($request, TwoFactorDetour::PaymentAccounts, route('tenants.payment-accounts.index', $tenant, absolute: false));
            }

            return redirect()->route('security.edit');
        }

        if ($this->shouldReconfirm($request)) {
            return $stayOnPage
                ? back()->withErrors(['two_factor_reconfirm' => __('account.two_factor_reconfirm.description')])
                : redirect()->guest(route('two-factor.reconfirm.show'));
        }

        return $next($request);
    }

    private function shouldReconfirm(Request $request): bool
    {
        $confirmedAt = Date::now()->unix() - $request->session()->get('auth.two_factor_confirmed_at', 0);

        return $confirmedAt > config('convive.two_factor.reconfirm_timeout');
    }
}
