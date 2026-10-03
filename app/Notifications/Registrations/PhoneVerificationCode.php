<?php

namespace App\Notifications\Registrations;

use App\Actions\Registrations\PhoneVerification;
use App\Support\WhatsApp\WhatsAppTemplate;
use Illuminate\Notifications\Notification;

/**
 * Le code de verification du telephone (SECURITY.md C3), envoye par WhatsApp seulement : c'est la
 * possession du numero qu'on verifie. Pas en file d'attente : l'invite attend ce code a l'ecran.
 */
class PhoneVerificationCode extends Notification
{
    public function __construct(public readonly string $code)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['whatsapp'];
    }

    /**
     * Get the WhatsApp template of this message and its variables, in the template's order.
     */
    public function whatsAppTemplate(mixed $notifiable): WhatsAppTemplate
    {
        // Un modele d'authentification ne porte que le code, chez Meta comme chez Twilio : sa duree
        // de validite se regle dans le modele lui-meme.
        return new WhatsAppTemplate('phone_code', [$this->code]);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return __('guest.whatsapp.phone_code', [
            'code' => $this->code,
            'minutes' => PhoneVerification::CodeMinutes,
        ]);
    }
}
