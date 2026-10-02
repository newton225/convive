<?php

namespace App\Actions\Events;

use App\Models\Event;

/**
 * Efface pour de bon les evenements supprimes depuis plus de trente jours, dans l'organisation
 * courante. Seul un brouillon se supprime (un evenement publie se cloture) : il ne porte ni
 * inscription ni preuve, mais il garde son visuel et ses tables tant qu'il reste dans la corbeille.
 *
 * Meme delai que l'effacement d'une organisation : trente jours pendant lesquels l'equipe Convive
 * peut encore le rendre, puis plus rien ne reste.
 */
class PurgeDeletedEvents
{
    public const RetentionDays = 30;

    /**
     * Returns how many events were erased.
     */
    public function handle(): int
    {
        return Event::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(self::RetentionDays))
            ->get()
            // Un par un : la suppression definitive d'un modele retire son visuel du disque, ce
            // qu'une suppression groupee ne ferait pas.
            ->each(fn (Event $event) => $event->forceDelete())
            ->count();
    }
}
