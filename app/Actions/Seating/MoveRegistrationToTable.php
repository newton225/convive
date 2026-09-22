<?php

namespace App\Actions\Seating;

use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Placement manuel d'une inscription par l'organisateur (README 2.6, ecran 21) : deplacement
 * vers une autre table, ou retrait de table quand `$table` est `null`. Distinct d'`AssignTable`,
 * l'attribution automatique jouee a la validation d'une preuve : un geste manuel exprime deja un
 * choix humain, il n'a donc pas a respecter le regroupement par unite ni les regles de
 * separation, seulement la capacite physique de la table visee.
 */
class MoveRegistrationToTable
{
    /**
     * Move (or unseat, when `$table` is null) the given registration.
     *
     * Meme verrou par evenement qu'`AssignTable`, pour la meme raison : deux placements manuels
     * concurrents sur la meme table ne doivent pas tous deux la croire disponible.
     */
    public function handle(Registration $registration, ?SeatingTable $table, User $actor): ?RegistrationTableAssignment
    {
        $event = $registration->event;

        return Cache::lock("event:{$event->id}:seating", 10)->block(5, function () use ($registration, $table, $actor) {
            $previous = RegistrationTableAssignment::where('registration_id', $registration->id)->first();

            if ($table === null) {
                if ($previous !== null) {
                    $this->log($previous, null, $actor);
                    $previous->delete();
                }

                return null;
            }

            $this->ensureRoomFor($table, $registration, $previous);

            $assignment = DB::transaction(function () use ($registration, $table, $previous) {
                $previous?->delete();

                return RegistrationTableAssignment::create([
                    'registration_id' => $registration->id,
                    'seating_table_id' => $table->id,
                    'assigned_manually' => true,
                ]);
            });

            $this->log($previous, $assignment, $actor);

            return $assignment;
        });
    }

    /**
     * Guard against seating more guests than the table has room for.
     *
     * The registration's own current seats (if already at this same table) are freed first :
     * reassigning a party to the table it already occupies must not count itself twice.
     */
    private function ensureRoomFor(SeatingTable $table, Registration $registration, ?RegistrationTableAssignment $previous): void
    {
        $table->load('assignments.registration');

        $remaining = $table->remainingCapacity();

        if ($previous !== null && $previous->seating_table_id === $table->id) {
            $remaining += $registration->party_size;
        }

        if ($remaining < $registration->party_size) {
            throw ValidationException::withMessages([
                'seating_table_id' => [__('seating.errors.table_full')],
            ]);
        }
    }

    private function log(?RegistrationTableAssignment $previous, ?RegistrationTableAssignment $next, User $actor): void
    {
        activity()
            ->performedOn($next ?? $previous)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties([
                'old' => ['seating_table_id' => $previous?->seating_table_id],
                'attributes' => ['seating_table_id' => $next?->seating_table_id],
            ])
            ->log('seating.moved');
    }
}
