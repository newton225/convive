<?php

namespace App\Support\WhatsApp;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

/**
 * Ne garde que les envois de Meta qui portent un message ecrit par quelqu'un : Meta previent aussi
 * de chaque message livre ou lu, sans interet ici.
 */
class IncomingMessagesProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        return IncomingMessages::from($request->json()->all()) !== [];
    }
}
