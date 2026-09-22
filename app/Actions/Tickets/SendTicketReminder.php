<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Notifications\Registrations\TicketReminder;
use Illuminate\Support\Facades\Notification;

/**
 * Rappel jour J moins 3 heures aux billets valides (README 2.7), etape 8 de « Ordre de
 * construction ».
 */
class SendTicketReminder
{
    /**
     * Send the reminder for the given ticket, or do nothing when it already was.
     */
    public function handle(Ticket $ticket): bool
    {
        if ($ticket->reminder_sent_at !== null) {
            return false;
        }

        $registration = $ticket->registration;
        $link = $registration->signedResumeUrl();

        if ($link === null) {
            return false;
        }

        Notification::route('whatsapp', $registration->phone)
            ->route('mail', $registration->email)
            ->notify(new TicketReminder($registration, $link));

        $ticket->update(['reminder_sent_at' => now()]);

        return true;
    }
}
