<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde la console d'exploitation (README section 3). Un compte qui n'en est pas operateur recoit
 * 404, jamais 403 : il n'apprend pas que la console existe, comme pour l'acces croise entre
 * organisations.
 *
 * La double authentification est obligatoire pour toute l'equipe editeur, quel que soit le profil :
 * la console voit a travers toutes les organisations. Meme reglage que pour les profils
 * d'organisation, donc coupe en local seulement (`convive.two_factor.enforced`).
 */
class EnsureConsoleOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::allows('console.access'), 404);

        if (config('convive.two_factor.enforced') && ! $request->user()?->hasEnabledTwoFactorAuthentication()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('console.two_factor_required'),
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
