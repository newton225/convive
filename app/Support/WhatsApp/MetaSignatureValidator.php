<?php

namespace App\Support\WhatsApp;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

/**
 * Meta signe chaque envoi de son webhook : `X-Hub-Signature-256: sha256=<HMAC du corps>`, avec le
 * secret de l'application. Sans secret regle, rien n'est accepte.
 *
 * Documentation : https://developers.facebook.com/docs/graph-api/webhooks/getting-started
 */
class MetaSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $secret = (string) config('services.whatsapp.inbound.app_secret');
        $signature = (string) $request->header($config->signatureHeaderName);

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }
}
