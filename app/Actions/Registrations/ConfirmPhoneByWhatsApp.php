<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\WhatsAppPhoneCheck;
use App\Notifications\Registrations\PhoneVerifiedByWhatsApp;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Notification;

/**
 * Un message recu sur le numero WhatsApp de Convive porte-t-il le code d'une verification en
 * attente, et vient-il du numero saisi a l'inscription ? Alors le telephone est verifie : seul son
 * detenteur peut envoyer un message WhatsApp depuis ce numero.
 *
 * Le code seul ne suffit pas : il s'affiche a l'ecran, n'importe qui peut le recopier. C'est
 * l'expediteur, garanti par WhatsApp, qui fait la preuve.
 */
class ConfirmPhoneByWhatsApp
{
    public function handle(string $from, string $text): bool
    {
        foreach (PhoneVerification::codesIn($text) as $code) {
            $check = WhatsAppPhoneCheck::where('code', $code)
                ->whereNull('verified_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($check === null || ! PhoneNumber::sameAsWhatsAppId($from, $check->phone)) {
                continue;
            }

            $verified = $check->tenant->run(function () use ($check) {
                $registration = Registration::find($check->registration_id);

                if ($registration === null || $registration->status !== RegistrationStatus::Draft) {
                    return false;
                }

                $registration->update(['phone_verified_at' => now(), 'phone_code_expires_at' => null]);

                return true;
            });

            if (! $verified) {
                continue;
            }

            $check->update(['verified_at' => now()]);

            // La personne vient d'ecrire : la reponse part dans la fenetre de 24 heures, sans modele.
            Notification::route('whatsapp', '+'.ltrim($from, '+'))->notifyNow(new PhoneVerifiedByWhatsApp);

            return true;
        }

        return false;
    }
}
