<?php

namespace App\Actions\Registrations;

use App\Enums\PhoneCodeResult;
use App\Models\Registration;
use App\Notifications\Registrations\PhoneVerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Verification du telephone par code avant la reservation (SECURITY.md C3), quand l'evenement
 * l'exige (`rule_phone_verification`). Le code n'est stocke qu'en empreinte, expire vite et ne
 * supporte que quelques essais : six chiffres restent sinon devinables par force brute.
 *
 * Les codes ne passent pas par le quota d'envois du plan (`GuestMessageQuota`) : les bloquer
 * fermerait l'inscription elle-meme, pas un simple rappel.
 */
class PhoneVerification
{
    public const CodeMinutes = 10;

    public const MaxAttempts = 5;

    /**
     * Issue a fresh code for the registration and send it by WhatsApp to its phone number.
     */
    public function send(Registration $registration): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $registration->update([
            'phone_code_hash' => Hash::make($code),
            'phone_code_expires_at' => now()->addMinutes(self::CodeMinutes),
            'phone_code_attempts' => 0,
        ]);

        Notification::route('whatsapp', $registration->phone)->notify(new PhoneVerificationCode($code));
    }

    /**
     * Check the code typed by the guest.
     *
     * Chaque essai rate est compte avant la comparaison suivante ; au-dela du plafond, le code est
     * efface et il faut en demander un nouveau, meme avec le bon.
     */
    public function verify(Registration $registration, string $code): PhoneCodeResult
    {
        if ($registration->phone_code_hash === null || $registration->phone_code_attempts >= self::MaxAttempts) {
            return PhoneCodeResult::TooManyAttempts;
        }

        if ($registration->phone_code_expires_at === null || $registration->phone_code_expires_at->isPast()) {
            return PhoneCodeResult::Expired;
        }

        if (! Hash::check($code, $registration->phone_code_hash)) {
            $attempts = $registration->phone_code_attempts + 1;

            $registration->update([
                'phone_code_attempts' => $attempts,
                'phone_code_hash' => $attempts >= self::MaxAttempts ? null : $registration->phone_code_hash,
            ]);

            return $attempts >= self::MaxAttempts ? PhoneCodeResult::TooManyAttempts : PhoneCodeResult::Invalid;
        }

        $registration->update([
            'phone_code_hash' => null,
            'phone_code_expires_at' => null,
            'phone_verified_at' => now(),
        ]);

        return PhoneCodeResult::Verified;
    }
}
