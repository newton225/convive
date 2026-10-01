<?php

namespace App\Actions\Console;

use App\Actions\Events\SaveEvent;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\ShowcaseEvent;
use App\Models\User;
use App\Notifications\Events\AnnouncementWithdrawnByEditor;
use App\Support\Console\ConsoleJournal;
use Illuminate\Support\Facades\Notification;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;

/**
 * Retirer de la vitrine une annonce jugee abusive (README section 3 et ecran 32), avec un motif
 * transmis a l'organisation. L'evenement lui-meme n'est pas touche : son lien public reste valide,
 * seule son annonce sur le site produit disparait.
 */
class WithdrawShowcaseAnnouncement
{
    public function __construct(private SaveEvent $saveEvent) {}

    public function handle(ShowcaseEvent $announcement, string $reason, User $actor): void
    {
        $tenant = $announcement->tenant;

        try {
            // Le retrait passe par l'action de l'organisation : son evenement cesse d'etre annonce
            // et la ligne de la vitrine disparait, d'un seul geste.
            $tenant->run(function () use ($announcement) {
                $event = Event::find($announcement->event_id);

                if ($event !== null) {
                    $this->saveEvent->withdraw($event);
                }
            });
        } catch (TenantDatabaseDoesNotExistException) {
            // L'echec survient dans `initialize()`, avant le `finally` de `Tenant::run()`.
            tenancy()->end();
        }

        // Une ligne orpheline (evenement supprime, base absente) part quand meme de la vitrine.
        ShowcaseEvent::whereKey($announcement->id)->delete();

        Notification::send(
            $tenant->membersWithPermission(TenantPermission::EventsAnnounce),
            new AnnouncementWithdrawnByEditor($announcement->name, $reason),
        );

        ConsoleJournal::record('announcement_withdrawn', $actor, $tenant, [
            'event_id' => $announcement->event_id,
            'event_name' => $announcement->name,
            'reason' => $reason,
        ]);
    }
}
