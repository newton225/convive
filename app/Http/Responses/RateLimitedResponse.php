<?php

namespace App\Http\Responses;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reponse d'une limite de debit atteinte (CLAUDE.md, « Limitation de debit » : « Les reponses
 * limitees renvoient un message utile, jamais un code technique brut »).
 *
 * Venue d'une page de l'application (un lien d'export, un formulaire), la requete y revient avec
 * un message qui dit quand reessayer : l'utilisateur reste la ou il etait. Sans page d'origine
 * (adresse saisie ou ouverte directement), une page lisible garde le statut 429, que les clients
 * automatises et les tests continuent de reconnaitre.
 */
final class RateLimitedResponse
{
    public static function for(Request $request, ThrottleRequestsException $exception): ?Response
    {
        if ($request->expectsJson()) {
            return null;
        }

        $retryAfter = (int) ($exception->getHeaders()['Retry-After'] ?? 60);
        $minutes = max(1, (int) ceil($retryAfter / 60));
        $message = trans_choice('common.rate_limit.retry_in', $minutes, ['minutes' => $minutes]);

        $previous = url()->previous();

        // Meme hote seulement : l'en-tete Referer vient du navigateur, il ne doit jamais faire de
        // cette reponse une redirection vers un site tiers.
        if ($request->headers->has('referer')
            && parse_url($previous, PHP_URL_HOST) === $request->getHost()
            && $previous !== $request->fullUrl()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return redirect()->to($previous)->withInput();
        }

        return response()->view('errors.rate-limited', ['message' => $message], 429, $exception->getHeaders());
    }
}
