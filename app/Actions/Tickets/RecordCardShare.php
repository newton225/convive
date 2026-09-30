<?php

namespace App\Actions\Tickets;

use App\Models\Registration;
use App\Models\Ticket;
use App\Models\User;

/**
 * Trace d'un lien de carte ou de billet sorti de l'application par l'organisateur (README 2.7) :
 * copie, ou envoi par son propre WhatsApp. Le lien donne acces au QR, donc permet d'entrer : on
 * doit pouvoir dire qui a eu en main le billet de qui.
 *
 * La trace est posee au moment du geste, cote navigateur, qui ne peut pas prouver que le lien a
 * vraiment ete colle ou envoye : c'est une intention d'envoi, pas une preuve de reception.
 */
class RecordCardShare
{
    public function handle(Registration $registration, ?Ticket $ticket, string $via, User $actor): void
    {
        activity()
            ->performedOn($registration)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties([
                'via' => $via,
                'ticket_id' => $ticket?->id,
                // Nul pour la carte de l'invite principal, qui donne acces a tout le groupe.
                'holder' => $ticket !== null ? $ticket->holder_name : $registration->name,
                'whole_group' => $ticket === null,
            ])
            ->log('registrations.card_shared');
    }
}
