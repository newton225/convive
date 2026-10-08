<?php

namespace App\Actions\Waitlist;

use App\Models\Event;

/**
 * Ajouter des places libere du stock comme une annulation (decision du proprietaire du projet,
 * 2026-10-08) : la liste d'attente est invitee des que la capacite augmente, sans attendre une
 * expiration ou une purge. Ne fait rien quand la capacite n'a pas grandi.
 */
class InviteWaitlistAfterGrowth
{
    public function handle(Event $event, int $capacityBefore): void
    {
        if ($event->capacity() <= $capacityBefore) {
            return;
        }

        while (app(PromoteNextWaitlistEntry::class)->handle($event) !== null) {
            // Meme boucle qu'apres une annulation : chaque invitation est une priorite d'acces.
        }
    }
}
