<?php

namespace App\Support;

use App\Contracts\WhatsAppSender;
use Illuminate\Support\Facades\Log;

/**
 * Implementation par defaut de `WhatsAppSender` : ecrit dans le journal applicatif plutot que
 * d'appeler un client HTTP reel, en l'absence d'identifiants WhatsApp Business API (CLAUDE.md,
 * « Paquets » : aucun paquet a installer pour un simple palliatif). Meme posture que
 * `MAIL_MAILER=log` par defaut : rien n'est perdu, rien n'est envoye pour de vrai. A remplacer
 * par un vrai client le jour ou l'organisation fournit ses identifiants, en ne changeant que
 * cette classe et sa liaison dans `AppServiceProvider`.
 */
class LogWhatsAppSender implements WhatsAppSender
{
    public function send(string $to, string $message): void
    {
        Log::info('WhatsApp (simule)', ['to' => $to, 'message' => $message]);
    }
}
