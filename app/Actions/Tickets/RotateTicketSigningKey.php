<?php

namespace App\Actions\Tickets;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rotation de la cle qui signe les billets d'un evenement (SECURITY.md C2), le geste a faire
 * quand un appareil d'agent est perdu ou qu'une fuite est suspectee.
 *
 * Tous les QR deja affiches, telecharges ou imprimes cessent d'etre valides : chaque billet se
 * resigne a son prochain affichage avec la nouvelle cle, et les appareils de scan recoivent la
 * nouvelle cle publique a leur prochaine synchronisation.
 */
class RotateTicketSigningKey
{
    public function handle(Event $event): Event
    {
        // Deux rotations simultanees ne doivent pas se marcher dessus : SQLite ignore
        // `lockForUpdate()` (CLAUDE.md, « Base de donnees »), d'ou le verrou applicatif.
        return Cache::lock("event:{$event->id}:ticket-key", 10)->block(5, function () use ($event) {
            return DB::transaction(function () use ($event) {
                $event->refresh();
                $previousVersion = $event->qr_key_version;

                $event->rotateSigningKeyPair();

                Ticket::whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
                    ->update(['key_version' => $event->qr_key_version]);

                activity()
                    ->performedOn($event)
                    ->event('updated')
                    ->withProperties([
                        'old' => ['qr_key_version' => $previousVersion],
                        'attributes' => ['qr_key_version' => $event->qr_key_version],
                    ])
                    ->log('event.ticket_key_rotated');

                return $event;
            });
        });
    }
}
