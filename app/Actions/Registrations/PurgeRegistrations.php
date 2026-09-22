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
 * Purge les inscriptions non finalisees d'un evenement (README 2.4), sur l'un de ses deux
 * declencheurs : l'echeance planifiee (`Event::purge_at`) ou l'epuisement des places par les
 * seules inscriptions confirmees. Chaque purge ecrit une entree dans le journal.
 */
class PurgeRegistrations
{
    /**
     * @return array{count: int, seatsFreed: int}
     */
    public function handle(Event $event): array
    {
        $query = $event->registrations()->whereIn('status', Registration::UnfinalizedStatuses);

        $count = $query->count();

        if ($count === 0) {
            return ['count' => 0, 'seatsFreed' => 0];
        }

        // Seules les reservations encore actives (`Held` non expirees) occupaient reellement
        // une place (voir `Registration::scopeOccupyingSeats`) : un brouillon, une reservation
        // deja expiree ou une preuve rejetee n'en liberent aucune en disparaissant.
        $seatsFreed = (int) (clone $query)
            ->where('status', RegistrationStatus::Held)
            ->where('held_until', '>', now())
            ->sum('party_size');

        $query->delete();

        activity()
            ->performedOn($event)
            ->event('purged')
            ->withProperties(['count' => $count, 'seats_freed' => $seatsFreed])
            ->log('registrations.purged');

        app(SendAlert::class)->toTenantMembers(
            NotificationType::RegistrationsPurged,
            ['count' => $count, 'event' => $event->name],
            route('tenants.events.registrations.index', [Tenant::current(), $event], absolute: false),
        );

        // Des qu'une place se libere, la liste d'attente avance (README 2.3) : on invite tant
        // qu'il reste du monde en attente et de la place pour au moins un de plus.
        if ($seatsFreed > 0) {
            while (app(PromoteNextWaitlistEntry::class)->handle($event) !== null) {
                //
            }
        }

        return ['count' => $count, 'seatsFreed' => $seatsFreed];
    }
}
