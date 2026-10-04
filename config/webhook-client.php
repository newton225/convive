<?php

use App\Jobs\ProcessWhatsAppWebhook;
use App\Support\WhatsApp\IncomingMessagesProfile;
use App\Support\WhatsApp\MetaSignatureValidator;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo;

return [
    'configs' => [
        [
            /*
             * Les messages des invites au numero WhatsApp de Convive, transmis par Meta : la
             * verification de leur telephone (`App\Actions\Registrations\PhoneVerification`).
             */
            'name' => 'whatsapp',

            // Le secret de l'application Meta, qui signe chaque envoi ; lu aussi par le validateur.
            'signing_secret' => env('WHATSAPP_META_APP_SECRET'),

            'signature_header_name' => 'X-Hub-Signature-256',

            // Meta prefixe la signature par `sha256=` : le validateur par defaut ne le lit pas.
            'signature_validator' => MetaSignatureValidator::class,

            // Seuls les messages entrants comptent : les accuses de livraison et de lecture ne sont
            // ni gardes ni traites.
            'webhook_profile' => IncomingMessagesProfile::class,

            'webhook_response' => DefaultRespondsTo::class,

            'webhook_model' => WebhookCall::class,

            'store_headers' => [],

            'store_attachments' => false,

            // Le traitement efface l'appel une fois fait : il porte le numero et le message d'un
            // invite, rien a garder au-dela.
            'process_webhook_job' => ProcessWhatsAppWebhook::class,
        ],
    ],

    /*
     * Un appel dont le traitement a echoue reste garde ce temps-la, pour comprendre la panne.
     */
    'delete_after_days' => 30,

    'add_unique_token_to_route_name' => false,
];
