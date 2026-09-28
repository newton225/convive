<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde la console d'exploitation (README section 3). Un compte qui n'en est pas operateur recoit
 * 404, jamais 403 : il n'apprend pas que la console existe, comme pour l'acces croise entre
 * organisations.
 */
class EnsureConsoleOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::allows('console.access'), 404);

        return $next($request);
    }
}
