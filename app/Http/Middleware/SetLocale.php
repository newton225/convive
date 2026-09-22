<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request));

        return $next($request);
    }

    /**
     * Resolve the locale to serve this request in.
     */
    protected function resolve(Request $request): string
    {
        if ($requested = Locale::sanitize($request->query('lang'))) {
            $request->session()->put('locale', $requested);

            return $requested;
        }

        if ($stored = Locale::sanitize($request->session()->get('locale'))) {
            return $stored;
        }

        // Le navigateur n'arbitre que pour le parcours invite : le back-office reste en francais
        // tant qu'un membre n'a pas choisi explicitement une autre langue.
        if (! $request->user()) {
            return Locale::sanitize($request->getPreferredLanguage(Locale::codes()))
                ?? Locale::Default;
        }

        return Locale::Default;
    }
}
