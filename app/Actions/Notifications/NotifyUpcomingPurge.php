<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;

/**
 * Previent l'equipe qu'une purge automatique (README 2.4) aura lieu dans moins de 24 heures, avec
 * le nombre de dossiers concernes (« Purge programmee dans 1 jour, 27 inscriptions concernees »,
 * prototype Convive.dc.html) : il reste un jour pour relancer les invites avant la suppression.
 *
 * Une seule fois par evenement (`purge_notice_sent_at`) : la tache planifiee repasse toutes les
 * heures. Une echeance deplacee plus tard ne repreviendra pas, deliberement : l'alerte annonce une
 * suppression imminente, pas chaque changement de date.
 */
class NotifyUpcomingPurge
{
    public const NoticeHours = 24;

    public function handle(Event $event): bool
    {
        if ($event->purge_notice_sent_at !== null || $event->purge_at === null
            || $event->purge_at->isPast() || $event->purge_at->gt(now()->addHours(self::NoticeHours))) {
            return false;
        }

        $concerned = Registration::where('event_id', $event->id)
            ->whereIn('status', Registration::UnfinalizedStatuses)
            ->count();

        if ($concerned === 0) {
            return false;
        }

        $event->purge_notice_sent_at = now();
        $event->save();

        app(SendAlert::class)->toTenantMembers(
            NotificationType::PurgeScheduled,
            ['event' => $event->name, 'count' => $concerned],
            route('tenants.events.registrations.index', [Tenant::current(), $event], absolute: false),
        );

        return true;
    }
}
