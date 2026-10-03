<?php

namespace App\Notifications\Registrations;

use App\Actions\Registrations\PhoneVerification;
use Illuminate\Notifications\Notification;

/**
 * Le code de verification du telephone (SECURITY.md C3), envoye par SMS seulement : c'est la
 * possession du numero qu'on verifie. Pas par WhatsApp (decision du proprietaire du projet,
 * 2026-10-03) : Meta refuse les modeles d'authentification a une entreprise non verifiee. Pas en
 * file d'attente : l'invite attend ce code a l'ecran.
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
        return ['sms'];
    }

    /**
     * Court, sans caractere hors de l'alphabet SMS de base : un seul SMS facture par code.
     */
    public function toSms(mixed $notifiable): string
    {
        return __('guest.sms.phone_code', [
            'code' => $this->code,
            'minutes' => PhoneVerification::CodeMinutes,
        ]);
    }
}
