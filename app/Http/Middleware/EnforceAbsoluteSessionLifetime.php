<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expiration absolue de la session (SECURITY.md, « Deconnexion et sessions »), en plus de
 * l'expiration par inactivite que `SESSION_LIFETIME` porte deja.
 *
 * L'heure de depart est posee a la connexion (ecouteur de `Login`, `AppServiceProvider`). Une
 * session authentifiee qui n'en porte pas, ouverte avant ce controle, la recoit a sa premiere
 * requete plutot que d'etre fermee sans prevenir.
 */
class EnforceAbsoluteSessionLifetime
{
    public const SessionKey = 'auth.session_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check() || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $startedAt = $session->get(self::SessionKey);

        if (! is_int($startedAt)) {
            $session->put(self::SessionKey, now()->getTimestamp());

            return $next($request);
        }

        $lifetimeMinutes = (int) config('convive.security.session_absolute_lifetime');

        if (now()->getTimestamp() - $startedAt <= $lifetimeMinutes * 60) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $session->invalidate();
        $session->regenerateToken();

        return redirect()->route('login')->with('status', __('account.session.expired'));
    }
}
