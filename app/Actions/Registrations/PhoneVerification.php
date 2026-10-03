<?php

namespace App\Actions\Registrations;

use App\Enums\PhoneCodeResult;
use App\Models\Registration;
use App\Notifications\Registrations\PhoneVerificationCode;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Verification du telephone par code avant la reservation (SECURITY.md C3), quand l'evenement
 * l'exige (`rule_phone_verification`), par SMS. Le code n'est stocke qu'en empreinte, expire vite et ne
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
     * Codes envoyes au meme numero en une heure, toutes inscriptions confondues : chaque SMS est
     * paye, et un robot qui recommencerait l'inscription en boucle viderait le credit d'Orange en
     * inondant le telephone d'un tiers.
     */
    public const MaxCodesPerHour = 5;

    /**
     * Determine whether a number gets a code at all. Ivoirien seulement (decision du proprietaire
     * du projet, 2026-10-03) : le SMS part par Orange Cote d'Ivoire, qui ne garantit pas l'envoi a
     * l'etranger, et un invite etranger ne doit pas rester bloque devant un code qui n'arrive pas.
     */
    public static function appliesTo(string $phone): bool
    {
        return PhoneNumber::normalizeIvorian($phone) !== null;
    }

    /**
     * Determine whether the number already received its hourly share of codes.
     */
    public static function exhausted(string $phone): bool
    {
        return RateLimiter::tooManyAttempts(self::limiterKey($phone), self::MaxCodesPerHour);
    }

    public static function limiterKey(string $phone): string
    {
        return 'phone-code-sms:'.(PhoneNumber::normalize($phone) ?? $phone);
    }

    /**
     * Issue a fresh code for the registration and send it by SMS to its phone number. Renvoie faux,
     * sans rien envoyer, quand le numero a deja recu son plafond de codes de l'heure.
     */
    public function send(Registration $registration): bool
    {
        if (self::exhausted($registration->phone)) {
            return false;
        }

        RateLimiter::hit(self::limiterKey($registration->phone), 3600);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $registration->update([
            'phone_code_hash' => Hash::make($code),
            'phone_code_expires_at' => now()->addMinutes(self::CodeMinutes),
            'phone_code_attempts' => 0,
        ]);

        Notification::route('sms', $registration->phone)->notify(new PhoneVerificationCode($code));

        return true;
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
