<?php

namespace App\Actions\Registrations;

use App\Actions\Notifications\SendAlert;
use App\Actions\Waitlist\PromoteNextWaitlistEntry;
use App\Enums\NotificationType;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;

/**
 * Marque `Expired` toute reservation dont le decompte est ecoule (README 2.1 et 2.2) : la
 * disponibilite en tient deja compte a la lecture (`Registration::scopeOccupyingSeats`), mais le
 * statut stocke doit refleter la realite pour l'affichage et les rapports, sans attendre que
 * l'invite revienne sur sa page.
 *
 * Extraite de la tache planifiee (`routes/console.php`) a l'etape 10 pour etre testable, et parce
 * qu'elle previent maintenant l'equipe : une alerte par evenement et par passage, pas une par
 * reservation, sans quoi une salle qui se vide ferait sonner la cloche des dizaines de fois.
 */
class ExpireHolds
{
    /**
     * Expire the event's lapsed holds and advance the waitlist.
     *
     * @return int le nombre de reservations expirees
     */
    public function handle(Event $event): int
    {
        $expired = Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::Held)
            ->where('held_until', '<=', now())
            ->update(['status' => RegistrationStatus::Expired]);

        if ($expired === 0) {
            return 0;
        }

        app(SendAlert::class)->toTenantMembers(
            NotificationType::HoldsExpired,
            ['count' => $expired, 'event' => $event->name],
            route('tenants.events.registrations.index', [Tenant::current(), $event], absolute: false),
        );

        // Des qu'une place se libere, la liste d'attente avance (README 2.3).
        while (app(PromoteNextWaitlistEntry::class)->handle($event) !== null) {
            //
        }

        return $expired;
    }
}
