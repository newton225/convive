<?php

namespace App\Actions\Registrations;

use App\Enums\PhoneCodeResult;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\WhatsAppPhoneCheck;
use App\Notifications\Registrations\PhoneVerificationCode;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Verification du telephone par code avant la reservation (SECURITY.md C3), quand l'evenement
 * l'exige (`rule_phone_verification`).
 *
 * Deux moyens. **Par WhatsApp** des que le numero de Convive recoit ses messages (decision du
 * proprietaire du projet, 2026-10-04) : l'invite envoie lui-meme un code au numero de Convive
 * (`ConfirmPhoneByWhatsApp`), rien ne lui est envoye. **Par SMS** sinon : un code lui est envoye.
 *
 * En SMS, Le code n'est stocke qu'en empreinte, expire vite et ne
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
     * Le code envoye par WhatsApp : huit caracteres, sans ceux qu'on confond (0 et O, 1 et I).
     * Un message peut s'egarer chez n'importe quelle organisation : le code doit etre unique sur
     * toute la plateforme, d'ou plus que six chiffres.
     */
    public const CodeAlphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const CodeLength = 8;

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
        // WhatsApp joint tous les pays : l'exemption des numeros etrangers ne vaut que pour le SMS.
        return self::viaWhatsApp()
            ? PhoneNumber::normalize($phone) !== null
            : PhoneNumber::normalizeIvorian($phone) !== null;
    }

    /**
     * Determine whether the check goes through a message the guest sends on WhatsApp : il faut le
     * numero de Convive et de quoi recevoir et authentifier les messages que Meta transmet.
     */
    public static function viaWhatsApp(): bool
    {
        $inbound = (array) config('services.whatsapp.inbound');

        return filled($inbound['number'] ?? null) && filled($inbound['verify_token'] ?? null) && filled($inbound['app_secret'] ?? null);
    }

    /**
     * Get the pending WhatsApp check of this registration, if any.
     */
    public static function whatsAppCheck(Registration $registration): ?WhatsAppPhoneCheck
    {
        return WhatsAppPhoneCheck::where('tenant_id', Tenant::current()?->getKey())
            ->where('registration_id', $registration->id)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * Get the WhatsApp link that opens a conversation with Convive's number, the message ready to send.
     */
    public static function whatsAppLink(string $code): string
    {
        $number = preg_replace('/\D+/', '', (string) config('services.whatsapp.inbound.number'));

        return 'https://wa.me/'.$number.'?text='.rawurlencode(__('guest.phone_verification.whatsapp_message', ['code' => $code]));
    }

    /**
     * Find the possible codes in a message : l'invite a pu ajouter du texte autour.
     *
     * @return array<int, string>
     */
    public static function codesIn(string $text): array
    {
        preg_match_all('/\b['.self::CodeAlphabet.']{'.self::CodeLength.'}\b/', mb_strtoupper($text), $matches);

        return array_values(array_unique($matches[0]));
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

        if (self::viaWhatsApp()) {
            $this->issueWhatsAppCheck($registration);

            return true;
        }

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
     * Issue a fresh code the guest will send on WhatsApp ; un code precedent cesse de valoir.
     */
    private function issueWhatsAppCheck(Registration $registration): void
    {
        $tenantId = Tenant::current()?->getKey();

        WhatsAppPhoneCheck::where('tenant_id', $tenantId)->where('registration_id', $registration->id)->delete();
        // Menage : les codes perimes depuis un jour ne servent plus a rien.
        WhatsAppPhoneCheck::where('expires_at', '<', now()->subDay())->delete();

        do {
            $code = '';

            for ($i = 0; $i < self::CodeLength; $i++) {
                $code .= self::CodeAlphabet[random_int(0, strlen(self::CodeAlphabet) - 1)];
            }
        } while (WhatsAppPhoneCheck::where('code', $code)->exists());

        $expiresAt = now()->addMinutes(self::CodeMinutes);

        WhatsAppPhoneCheck::create([
            'code' => $code,
            'tenant_id' => $tenantId,
            'registration_id' => $registration->id,
            'phone' => $registration->phone,
            'expires_at' => $expiresAt,
        ]);

        $registration->update(['phone_code_hash' => null, 'phone_code_expires_at' => $expiresAt, 'phone_code_attempts' => 0]);
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
