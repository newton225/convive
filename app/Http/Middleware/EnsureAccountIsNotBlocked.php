<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte bloque par l'equipe Convive ne reste pas connecte (README section 3). Le blocage ferme
 * deja les sessions ouvertes ; ce controle arrete celle que la personne ouvrirait ensuite, et le lui
 * dit, au lieu de la laisser entrer.
 *
 * Le message ne donne pas le motif : il est a l'usage de l'equipe Convive, que la personne contacte.
 */
class EnsureAccountIsNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBlocked()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', __('account.login.blocked'));
        }

        return $next($request);
    }
}
