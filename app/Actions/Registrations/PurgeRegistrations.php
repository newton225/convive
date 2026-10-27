<?php

namespace App\Actions\Registrations;

use App\Actions\Notifications\SendAlert;
use App\Actions\Waitlist\PromoteNextWaitlistEntry;
use App\Enums\NotificationType;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\RejectedProofTrace;
use App\Models\Tenant;

/**
 * Purge les inscriptions non finalisees d'un evenement (README 2.4), sur l'un de ses deux
 * declencheurs : l'echeance planifiee (`Event::purge_at`) ou l'epuisement des places par les
 * seules inscriptions confirmees. Chaque purge ecrit une entree dans le journal.
 */
class PurgeRegistrations
{
    /**
     * `$only` limite la purge a ces dossiers (geste manuel sur une ou quelques lignes) : ceux qui
     * ne sont pas de cet evenement ou qui sont finalises sont ignores, jamais effaces.
     * `$keepActiveHolds` epargne les reservations dont le decompte tourne encore : un invite est
     * peut-etre en train de payer. Les purges automatiques (echeance, epuisement) gardent le
     * comportement d'origine et ne l'utilisent pas.
     *
     * @param  array<int, int>|null  $only
     * @return array{count: int, seatsFreed: int}
     */
    public function handle(Event $event, ?array $only = null, bool $keepActiveHolds = false): array
    {
        $query = $event->registrations()->whereIn('status', Registration::UnfinalizedStatuses);

        if ($only !== null) {
            $query->whereIn('id', $only);
        }

        if ($keepActiveHolds) {
            $query->whereNot(fn ($active) => $active
                ->where('status', RegistrationStatus::Held)
                ->where('held_until', '>', now()));
        }

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

        // Les preuves d'abord, une par une : la suppression d'un modele retire sa capture du
        // disque. La suppression groupee ci-dessous emporte bien leurs lignes (cle etrangere en
        // cascade), mais sans passer par le modele : les captures, qui portent le nom, le numero et
        // le montant de l'invite, resteraient sur le disque sans plus rien pour les retrouver.
        //
        // Une preuve rejetee laisse une trace anonyme (decision du 2026-10-27) : sans elle, la meme fausse
        // reference ou la meme capture, redeposee, ne serait plus reconnue.
        PaymentProof::whereIn('registration_id', (clone $query)->select('id'))
            ->get()
            ->each(function (PaymentProof $proof) use ($event) {
                if ($proof->rejection_reason !== null) {
                    RejectedProofTrace::create([
                        'event_id' => $event->id,
                        'reference' => $proof->reference,
                        'reference_key' => $proof->reference_key,
                        'perceptual_hash' => $proof->perceptual_hash,
                        'rejection_reason' => $proof->rejection_reason,
                        'submitted_at' => $proof->created_at,
                    ]);
                }

                $proof->delete();
            });

        // Les references seules (jamais les noms ni les numeros) : de quoi retrouver ce qui a ete
        // purge sans conserver de donnees personnelles dans le journal.
        $references = (clone $query)->whereNotNull('reference')->pluck('reference')->all();

        $query->delete();

        activity()
            ->performedOn($event)
            ->event('purged')
            ->withProperties([
                'count' => $count,
                'seats_freed' => $seatsFreed,
                'references' => $references,
            ])
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
