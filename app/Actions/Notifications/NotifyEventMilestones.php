<?php

namespace App\Actions\Notifications;

use App\Enums\EventStatus;
use App\Enums\NotificationType;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;

/**
 * Previent l'equipe que la fin d'un evenement ou sa date limite d'inscription vient d'etre atteinte
 * (decision du 2026-10-12). Une seule fois chacune (`ended_alerted_at`,
 * `registrations_closed_alerted_at`) : la tache planifiee repasse toutes les quinze minutes.
 *
 * Un evenement cloture a la main n'est pas concerne : la personne qui l'a cloture le sait, et
 * `SaveEvent::close()` previent deja les autres. Une fin atteinte couvre la date limite : les deux
 * ne sont jamais annoncees ensemble.
 */
class NotifyEventMilestones
{
    public function handle(Event $event): void
    {
        if (! $event->isPublished() || $event->status === EventStatus::Closed) {
            return;
        }

        $confirmed = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->count();

        if ($event->ended_alerted_at === null && $event->hasEnded()) {
            $event->ended_alerted_at = now();
            $event->registrations_closed_alerted_at ??= now();
            $event->save();

            $this->alert(NotificationType::EventEnded, $event, $confirmed);

            return;
        }

        if ($event->registrations_closed_alerted_at === null && $event->registrationDeadlineHasPassed()) {
            $event->registrations_closed_alerted_at = now();
            $event->save();

            $this->alert(NotificationType::RegistrationsClosed, $event, $confirmed);
        }
    }

    private function alert(NotificationType $type, Event $event, int $confirmed): void
    {
        app(SendAlert::class)->toTenantMembers(
            $type,
            ['event' => $event->name, 'count' => $confirmed],
            route('tenants.events.registrations.index', [Tenant::current(), $event], absolute: false),
        );
    }
}
