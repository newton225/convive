<?php

namespace App\Actions\Tickets;

use App\Enums\ReminderCheckpoint;
use App\Models\Registration;
use App\Notifications\Registrations\ProofReminder;
use App\Support\GuestMessageQuota;
use Illuminate\Support\Facades\Notification;

/**
 * Rappel J-7, J-2 ou J-1 a une inscription sans preuve encore validee (README 2.7), etape 8 de
 * « Ordre de construction ».
 */
class SendProofReminder
{
    /**
     * Send the reminder for the given checkpoint, or do nothing when it already was.
     *
     * Idempotent par la colonne du checkpoint : une tache planifiee rejouee ne renvoie jamais le
     * meme rappel deux fois, meme si elle passe plusieurs fois pendant que la fenetre reste
     * ouverte.
     */
    public function handle(Registration $registration, ReminderCheckpoint $checkpoint): bool
    {
        if ($registration->getAttribute($checkpoint->column()) !== null) {
            return false;
        }

        $link = $registration->signedResumeUrl();

        if ($link === null) {
            return false;
        }

        // Quota d'envois du plan (SECURITY.md H5) : un message refuse n'est pas marque envoye.
        if (! GuestMessageQuota::allows()) {
            return false;
        }

        Notification::route('whatsapp', $registration->phone)
            ->route('mail', $registration->email)
            ->notify(new ProofReminder($registration, $link));

        $registration->update([$checkpoint->column() => now()]);
        GuestMessageQuota::record();

        return true;
    }
}
