<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * La reservation en cours de ce navigateur sur un evenement (demande du proprietaire du projet,
 * 2026-10-03) : un invite revenu sur le formulaire par le bouton « retour » se voit proposer de la
 * reprendre, plutot que de buter sur « une reservation est deja en cours pour ce numero ».
 *
 * Le jeton de reprise est garde dans la session, chiffre : la session vit dans la base centrale,
 * et ce jeton donne acces au dossier (il n'est garde qu'en empreinte sur l'inscription).
 */
final class OngoingReservation
{
    public static function remember(Event $event, string $resumeToken): void
    {
        session()->put(self::key($event), Crypt::encryptString($resumeToken));
    }

    /**
     * Get the resume token and status of this browser's reservation on the event, while it is
     * still under way : brouillon en attente du code, places retenues, preuve en attente de
     * validation ou refusee (l'invite en depose une autre). Une fois validee, expiree ou annulee,
     * elle est oubliee.
     *
     * @return array{resume: string, status: RegistrationStatus}|null
     */
    public static function find(Event $event): ?array
    {
        $stored = session()->get(self::key($event));

        if (! is_string($stored)) {
            return null;
        }

        try {
            $resume = Crypt::decryptString($stored);
        } catch (DecryptException) {
            self::forget($event);

            return null;
        }

        $registration = Registration::where('event_id', $event->id)
            ->where('resume_token_hash', Registration::hashResumeToken($resume))
            ->first();

        if ($registration === null || ! self::isUnderWay($registration)) {
            self::forget($event);

            return null;
        }

        return ['resume' => $resume, 'status' => $registration->status];
    }

    public static function forget(Event $event): void
    {
        session()->forget(self::key($event));
    }

    private static function isUnderWay(Registration $registration): bool
    {
        return match ($registration->status) {
            RegistrationStatus::Draft, RegistrationStatus::ProofSubmitted, RegistrationStatus::ProofRejected => true,
            RegistrationStatus::Held => ! $registration->holdHasExpired(),
            default => false,
        };
    }

    /**
     * Par le jeton public de l'evenement, jamais son identifiant : deux organisations ont chacune
     * un evenement numero 1, chacune dans sa base.
     */
    private static function key(Event $event): string
    {
        return 'ongoing_reservations.'.$event->public_token;
    }
}
