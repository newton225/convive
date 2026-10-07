<?php

namespace App\Actions\Registrations;

use App\Actions\Seating\AssignTable;
use App\Actions\Tickets\IssueTicket;
use App\Actions\Tickets\SendInvitationCard;
use App\Models\Registration;

/**
 * Ce qui suit la confirmation d'une inscription, quelle qu'en soit la voie : preuve validee
 * (`ValidatePaymentProof`) ou evenement gratuit confirme a la reservation (`HoldRegistration`).
 * Attribution de table, emission du billet, envoi de la carte si son echeance est passee : trois
 * actions idempotentes, qui peuvent donc etre retentees sans risque.
 */
class FinalizeConfirmedRegistration
{
    public function handle(Registration $registration): void
    {
        // Attribution automatique, sauf si l'organisateur l'a coupee (README ecran 24). Une
        // inscription confirmee sans table reste un etat valide : le placement manuel reste
        // possible (README 2.6, ecran 21).
        if ($registration->event->rule_auto_seating) {
            app(AssignTable::class)->handle($registration);
        }

        app(IssueTicket::class)->handle($registration);

        $this->sendCardIfDue($registration);
    }

    /**
     * Send the invitation card immediately when the event's scheduled send date has already
     * passed ; otherwise leave it to the scheduled task that watches that deadline. Skipped
     * entirely when the organiser disabled scheduled sending for this event (README ecran 24).
     */
    private function sendCardIfDue(Registration $registration): void
    {
        $event = $registration->event;
        $sendAt = $event->invitations_send_at;

        if ($event->rule_scheduled_send && $sendAt !== null && $sendAt->isPast()) {
            app(SendInvitationCard::class)->handle($registration);
        }
    }
}
