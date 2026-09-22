<?php

namespace App\Actions\Seating;

use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\UnitSeparationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Attribution automatique des tables (README 2.6), a la validation d'une preuve, dans l'ordre
 * de validation : etape 6 de « Ordre de construction ».
 *
 * Un accompagnateur est toujours assis avec son invitant : l'attribution porte sur l'inscription
 * entiere (participant plus accompagnateurs), jamais sur un occupant individuel. Si aucune table
 * n'a assez de places libres pour le groupe entier, l'inscription reste confirmee mais non
 * placee : un placement manuel reste possible (README 2.6), a construire avec le plan de salle
 * (ecran 21).
 */
class AssignTable
{
    /**
     * Attempt to seat the given registration.
     *
     * Idempotent : une inscription deja assise n'est pas deplacee. `Cache::lock()` par
     * evenement serialise la lecture des places restantes et l'ecriture de l'attribution, meme
     * raison qu'`App\Actions\Registrations\HoldRegistration` (SQLite ignore
     * `lockForUpdate()`) : deux validations concurrentes sur le meme evenement, y compris sur la
     * meme inscription (rejeu, double clic), ne peuvent pas choisir la meme table en meme temps
     * ni asseoir deux fois la meme inscription.
     *
     * La verification d'idempotence interroge la base, a l'interieur du verrou, plutot que la
     * relation chargee sur `$registration` : l'accesseur magique met le resultat en cache sur
     * l'instance, et un appelant qui reutilise la meme instance entre deux appels verrait un
     * `null` perime meme apres la creation de l'attribution par le premier appel.
     */
    public function handle(Registration $registration): ?RegistrationTableAssignment
    {
        $event = $registration->event;

        return Cache::lock("event:{$event->id}:seating", 10)->block(5, function () use ($event, $registration) {
            $existing = RegistrationTableAssignment::where('registration_id', $registration->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            $this->ensureTablesExist($event);

            $table = $this->pickTable($event, $registration);

            if ($table === null) {
                return null;
            }

            return DB::transaction(fn () => RegistrationTableAssignment::create([
                'registration_id' => $registration->id,
                'seating_table_id' => $table->id,
                'assigned_manually' => false,
            ]));
        });
    }

    /**
     * Create whatever tables are still missing for the event's current room plan.
     *
     * Provisionnees paresseusement plutot qu'a la sauvegarde de l'evenement : `table_count`
     * peut encore changer avant la premiere validation.
     */
    private function ensureTablesExist(Event $event): void
    {
        $existingNumbers = SeatingTable::where('event_id', $event->id)->pluck('number')->all();

        for ($number = 1; $number <= $event->table_count; $number++) {
            if (! in_array($number, $existingNumbers, true)) {
                SeatingTable::create([
                    'event_id' => $event->id,
                    'number' => $number,
                    'capacity' => $event->seats_per_table,
                ]);
            }
        }
    }

    /**
     * Pick the best table for the registration, or null when none has room.
     *
     * Ordre des regles (README 2.6) : une table reservee pour cette unite d'abord, puis le
     * regroupement par unite sur les tables non reservees, puis la premiere table venue.
     * Les tables reservees pour une AUTRE unite ne sont jamais candidates. Les regles de
     * separation entre unites ecartent une table des le depart, avant meme d'appliquer ces
     * preferences.
     */
    private function pickTable(Event $event, Registration $registration): ?SeatingTable
    {
        $separations = UnitSeparationRule::where('event_id', $event->id)->get();

        $candidates = SeatingTable::where('event_id', $event->id)
            ->with('assignments.registration')
            ->ordered()
            ->get()
            ->filter(fn (SeatingTable $table) => $table->remainingCapacity() >= $registration->party_size)
            ->reject(fn (SeatingTable $table) => $this->isSeparatedFrom($table, $registration->unit_id, $separations));

        $reserved = $candidates->first(fn (SeatingTable $table) => $table->reserved_unit_id === $registration->unit_id);

        if ($reserved !== null) {
            return $reserved;
        }

        $open = $candidates->filter(fn (SeatingTable $table) => $table->reserved_unit_id === null);

        $grouped = $open->first(
            fn (SeatingTable $table) => $table->occupantUnitIds()->contains($registration->unit_id),
        );

        return $grouped ?? $open->first();
    }

    /**
     * Determine whether seating this unit at the table would break a separation rule against
     * a unit already seated there.
     *
     * @param  Collection<int, UnitSeparationRule>  $separations
     */
    private function isSeparatedFrom(SeatingTable $table, int $unitId, Collection $separations): bool
    {
        return $table->occupantUnitIds()
            ->contains(fn (int $occupantUnitId) => $separations->contains(
                fn (UnitSeparationRule $rule) => $rule->concerns($unitId, $occupantUnitId),
            ));
    }
}
