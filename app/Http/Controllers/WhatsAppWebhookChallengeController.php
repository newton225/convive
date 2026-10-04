<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Meta valide l'adresse du webhook avant de l'utiliser : une requete GET avec `hub.mode`,
 * `hub.verify_token` (le jeton convenu dans ses reglages) et `hub.challenge`, a renvoyer tel quel.
 * PHP lit les points de ces noms comme des soulignes (`hub_mode`...).
 */
class WhatsAppWebhookChallengeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $expected = (string) config('services.whatsapp.inbound.verify_token');

        abort_unless(
            $expected !== ''
                && $request->query('hub_mode') === 'subscribe'
                && hash_equals($expected, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }
}
