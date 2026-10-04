<?php

namespace App\Jobs;

use App\Actions\Registrations\ConfirmPhoneByWhatsApp;
use App\Support\WhatsApp\IncomingMessages;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

/**
 * Traite les messages recus sur le numero WhatsApp de Convive : chacun peut porter le code de
 * verification d'un telephone (`ConfirmPhoneByWhatsApp`).
 */
class ProcessWhatsAppWebhook extends ProcessWebhookJob
{
    public function handle(ConfirmPhoneByWhatsApp $confirm): void
    {
        foreach (IncomingMessages::from((array) $this->webhookCall->payload) as $message) {
            $confirm->handle($message['from'], $message['text']);
        }

        // L'appel porte le numero et le message d'un invite : une fois traite, rien a garder.
        $this->webhookCall->delete();
    }
}
