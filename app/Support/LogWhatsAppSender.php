<?php

namespace App\Support;

use App\Contracts\WhatsAppSender;
use App\Support\WhatsApp\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;

/**
 * Implementation par defaut de `WhatsAppSender` : ecrit dans le journal applicatif plutot que
 * d'appeler un client HTTP reel, en l'absence d'identifiants WhatsApp Business API (CLAUDE.md,
 * « Paquets » : aucun paquet a installer pour un simple palliatif). Meme posture que
 * `MAIL_MAILER=log` par defaut : rien n'est perdu, rien n'est envoye pour de vrai. A remplacer
 * par un vrai client le jour ou l'organisation fournit ses identifiants, en ne changeant que
 * cette classe et sa liaison dans `AppServiceProvider`.
 *
 * Hors production, le message complet est journalise : c'est tout l'interet du palliatif pour
 * verifier un envoi en developpement. En production, ni le numero, ni le message n'y figurent
 * (SECURITY.md M7) : le message porte le lien signe de l'inscription, un secret qui donne acces
 * au dossier de l'invite, et le numero est une donnee personnelle.
 */
class LogWhatsAppSender implements WhatsAppSender
{
    public function send(string $to, string $message, ?WhatsAppTemplate $template = null): void
    {
        if (app()->isProduction()) {
            Log::info('WhatsApp (simule)', ['to' => self::mask($to), 'length' => mb_strlen($message)]);

            return;
        }

        Log::info('WhatsApp (simule)', ['to' => $to, 'message' => $message]);
    }

    public function delivers(): bool
    {
        return false;
    }

    /**
     * Keep only the last two digits, enough to tell two sends apart without identifying anyone.
     */
    private static function mask(string $number): string
    {
        return str_repeat('*', max(0, mb_strlen($number) - 2)).mb_substr($number, -2);
    }
}
