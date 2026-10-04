<?php

namespace App\Notifications\Registrations;

use Illuminate\Notifications\Notification;

/**
 * La reponse a l'invite qui vient d'envoyer son code de verification par WhatsApp : il sait que
 * c'est fait, et qu'il peut revenir a la page d'inscription. Envoyee juste apres son message, dans
 * la fenetre de 24 heures : aucun modele a faire approuver.
 */
class PhoneVerifiedByWhatsApp extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return __('guest.whatsapp.phone_verified');
    }
}
