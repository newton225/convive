<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * La protection anti-robot du formulaire d'inscription (decision du proprietaire du projet,
 * 2026-10-03) : Cloudflare Turnstile, gratuit, par le client HTTP de Laravel. Elle remplace le
 * code par SMS, mis de cote, contre les robots qui rempliraient le formulaire pour bloquer toutes
 * les places (SECURITY.md C3).
 *
 * Documentation : https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 */
final class BotCheck
{
    /**
     * Le champ que le widget de Cloudflare ajoute de lui-meme au formulaire.
     */
    public const Field = 'cf-turnstile-response';

    public const ScriptOrigin = 'https://challenges.cloudflare.com';

    /**
     * Get the public key of the widget, or null when the keys are not both configured : sans
     * elles, aucune verification n'est demandee, pour ne rien bloquer en developpement.
     */
    public static function siteKey(): ?string
    {
        $siteKey = config('services.turnstile.site_key');
        $secretKey = config('services.turnstile.secret_key');

        return filled($siteKey) && filled($secretKey) ? (string) $siteKey : null;
    }

    /**
     * Determine whether the registration form of this event asks for the check.
     */
    public static function appliesTo(Event $event): bool
    {
        return $event->rule_bot_protection && self::siteKey() !== null;
    }

    /**
     * Ask Cloudflare whether the token the browser received is valid.
     *
     * Cloudflare injoignable laisse passer, journalise : une panne chez lui ne doit pas fermer les
     * inscriptions de tous les evenements, et les autres protections (une reservation par numero,
     * plafonds par adresse IP et par reseau) restent en place. Un refus explicite, lui, bloque.
     */
    public static function passes(string $token, ?string $ip): bool
    {
        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::ScriptOrigin.'/turnstile/v0/siteverify', [
                    'secret' => (string) config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Verification anti-robot injoignable, inscription laissee passer.', ['error' => $exception->getMessage()]);

            return true;
        }

        if ($response->serverError()) {
            Log::warning('Verification anti-robot en panne, inscription laissee passer.', ['status' => $response->status()]);

            return true;
        }

        return $response->json('success') === true;
    }
}
