<?php

namespace App\Actions\Waitlist;

use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\WaitlistEntry;

/**
 * Des qu'une place se libere (purge, expiration, annulation), invite le premier de la liste
 * d'attente (README 2.3) : un lien valable six heures pour finaliser.
 *
 * Ne verifie pas que le groupe invite tient dans les places disponibles : l'invite n'est
 * qu'une priorite d'acces, pas une garantie. `App\Actions\Registrations\HoldRegistration`
 * revérifiera le stock au moment de la finalisation, comme toute autre tentative de reservation
 * (README 2.2).
 */
class PromoteNextWaitlistEntry
{
    public function handle(Event $event): ?WaitlistEntry
    {
        if ($event->remainingSeats() <= 0) {
            return null;
        }

        $next = WaitlistEntry::where('event_id', $event->id)
            ->where('status', WaitlistStatus::Waiting)
            ->orderBy('id')
            ->first();

        if (! $next) {
            return null;
        }

        $next->update([
            'status' => WaitlistStatus::Invited,
            'invited_at' => now(),
            'expires_at' => now()->addHours(WaitlistEntry::InviteDurationHours),
        ]);

        return $next;
    }
}
