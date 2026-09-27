<?php

namespace App\Actions\Tickets;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Notifications\Registrations\InvitationCard;
use App\Support\GuestMessageQuota;
use Illuminate\Support\Facades\Notification;

/**
 * Envoi de la carte d'invitation (README 2.7, ecran 7), etape 8 de « Ordre de construction ».
 * Appelee juste apres la confirmation quand l'echeance programmee de l'evenement est deja
 * passee (voir `App\Actions\PaymentProofs\ValidatePaymentProof`), et par la tache planifiee qui
 * surveille cette echeance pour les inscriptions confirmees avant elle.
 */
class SendInvitationCard
{
    /**
     * Send the card for the given registration, or do nothing when it is not due yet.
     *
     * Idempotent via `card_sent_at` : une inscription deja servie ne l'est pas une seconde fois,
     * qu'elle soit retentee par le meme appelant ou trouvee par les deux (la validation, puis la
     * tache planifiee qui balaie les inscriptions confirmees avant elle).
     */
    public function handle(Registration $registration): bool
    {
        if ($registration->status !== RegistrationStatus::Confirmed || $registration->card_sent_at !== null) {
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
            ->notify(new InvitationCard($registration, $link));

        $registration->update(['card_sent_at' => now()]);
        GuestMessageQuota::record();

        return true;
    }
}
