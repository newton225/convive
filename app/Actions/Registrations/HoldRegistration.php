<?php

namespace App\Actions\Registrations;

use App\Actions\Notifications\SendAlert;
use App\Enums\NotificationType;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Support\PlanLimits;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Fait passer une inscription de `Draft` (ou `Expired`, sur relance) a `Held`, en verifiant la
 * disponibilite sous verrou : le coeur du noyau de reservation, etape 5 de « Ordre de
 * construction » (CLAUDE.md).
 *
 * `lockForUpdate()` est sans effet en SQLite (CLAUDE.md, « Base de donnees ») : la garantie
 * contre la survente repose ici sur deux mecanismes complementaires, pas un seul.
 *
 * 1. `Cache::lock()`, un verrou applicatif par evenement, serialise le calcul de disponibilite
 *    et l'ecriture de la reservation : deux requetes simultanees sur la derniere place
 *    n'entrent jamais dans ce bloc en meme temps.
 * 2. La contrainte d'unicite reelle sur `(event_id, hold_sequence)` (voir la migration qui
 *    l'ajoute) est le filet de secours si le verrou ne serialisait pas vraiment (pilote de
 *    cache non partage entre plusieurs serveurs applicatifs, par exemple) : l'une des deux
 *    ecritures concurrentes echouerait alors a la base plutot que de survendre en silence.
 */
class HoldRegistration
{
    /**
     * Nombre de tentatives avant d'abandonner si c'est la contrainte d'unicite, plutot que le
     * verrou, qui finit par serialiser deux tentatives concurrentes.
     */
    private const MaxAttempts = 3;

    /**
     * Attempt to hold seats for the given registration.
     *
     * @return bool whether the hold succeeded ; false means the event has no room left.
     */
    public function handle(Event $event, Registration $registration): bool
    {
        // Le plafond d'inscrits du plan (README section 3) : au-dela, l'evenement ne prend plus
        // de reservation, comme s'il etait complet.
        $tenant = Tenant::current();

        if ($tenant !== null && ! PlanLimits::for($tenant)->canRegister($registration->party_size)) {
            return false;
        }

        // Une relance apres expiration compte l'expiration precedente (SECURITY.md C3) : `held_until`
        // va etre reecrit, et le delai croissant par numero ne la verrait plus.
        $lapsedBefore = $registration->status === RegistrationStatus::Expired || $registration->holdHasExpired();

        $lock = Cache::lock("event:{$event->id}:seats", 10);

        return $lock->block(5, function () use ($event, $registration, $lapsedBefore) {
            for ($attempt = 0; $attempt < self::MaxAttempts; $attempt++) {
                if ($registration->party_size > $event->remainingSeats()) {
                    return false;
                }

                try {
                    $held = DB::transaction(function () use ($event, $registration, $lapsedBefore) {
                        $nextSequence = 1 + (int) Registration::where('event_id', $event->id)->max('hold_sequence');

                        // Evenement gratuit (decision du 2026-10-07) : rien a verser, la place
                        // est confirmee sous le meme verrou, sans decompte ni preuve.
                        $registration->update([
                            'status' => $event->isFree() ? RegistrationStatus::Confirmed : RegistrationStatus::Held,
                            'held_until' => $event->isFree() ? null : now()->addMinutes($event->hold_duration_minutes),
                            'hold_sequence' => $nextSequence,
                            'lapsed_holds_count' => $registration->lapsed_holds_count + ($lapsedBefore ? 1 : 0),
                        ]);

                        return true;
                    });
                } catch (QueryException $exception) {
                    // Violation d'unicite sur hold_sequence : quelqu'un d'autre a reclame ce
                    // jeton entre la lecture du maximum et l'ecriture, malgre le verrou. On
                    // recalcule la disponibilite et on retente plutot que d'echouer.
                    continue;
                }

                // Hors du `try` : une erreur d'envoi de l'alerte ne doit jamais etre prise pour
                // une collision de sequence et relancer une reservation deja reussie.
                $this->alertIfSeatsExhausted($event);
                $this->alertIfSeatsLow($event);

                if ($event->isFree()) {
                    $this->confirmFree($registration);
                }

                return $held;
            }

            return false;
        });
    }

    /**
     * Journal and follow-up of a free registration, confirmed at once : table, ticket and card,
     * exactly as after a validated proof (`FinalizeConfirmedRegistration`).
     */
    private function confirmFree(Registration $registration): void
    {
        activity()
            ->performedOn($registration)
            ->event('updated')
            ->withProperties(['attributes' => ['status' => RegistrationStatus::Confirmed->value]])
            ->log('registration.confirmed_free');

        app(FinalizeConfirmedRegistration::class)->handle($registration->fresh() ?? $registration);
    }

    /**
     * Tell the team once when the stock drops under the low-seats threshold (« Il reste 68
     * places », prototype Convive.dc.html), before it runs out. Une seule fois par evenement
     * (`seats_low_alerted_at`) : chaque reservation suivante ne doit pas refaire sonner la cloche.
     */
    private function alertIfSeatsLow(Event $event): void
    {
        $capacity = $event->capacity();
        $remaining = $event->remainingSeats();

        if ($event->seats_low_alerted_at !== null || $capacity === 0 || $remaining === 0
            || $remaining > $capacity * (float) config('convive.alerts.seats_low_ratio')) {
            return;
        }

        $event->seats_low_alerted_at = now();
        $event->save();

        app(SendAlert::class)->toTenantMembers(
            NotificationType::SeatsLow,
            ['event' => $event->name, 'count' => $remaining],
            route('tenants.events.registrations.index', [Tenant::current(), $event], absolute: false),
        );
    }

    /**
     * Tell the team when this hold took the last seat (README section 5, « places epuisees »).
     *
     * Une reservation ne reussit que s'il restait de la place : arriver a zero ici est donc bien la
     * transition, et elle ne se produit qu'une fois par remplissage.
     */
    private function alertIfSeatsExhausted(Event $event): void
    {
        if ($event->remainingSeats() > 0) {
            return;
        }

        app(SendAlert::class)->toTenantMembers(
            NotificationType::SeatsExhausted,
            ['event' => $event->name],
            route('tenants.events.registrations.index', [Tenant::current(), $event], absolute: false),
        );
    }
}
