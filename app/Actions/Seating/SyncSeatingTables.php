<?php

namespace App\Actions\Seating;

use App\Models\Event;
use App\Models\SeatingTable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Le plan de salle d'un evenement, tel que le formulaire le decrit (README ecran 13, decision du
 * 2026-09-29) : des groupes de tables de tailles differentes, « 3 tables de 12, 20 tables de 8 ».
 *
 * Les tables sont numerotees groupe apres groupe et creees des l'enregistrement de l'evenement :
 * c'est leur capacite, table par table, qui fait la capacite de l'evenement (`Event::capacity()`).
 * Un invite deja place ne perd jamais sa chaise : une table occupee ne se retire pas, et ne
 * descend pas sous le nombre de personnes qu'elle accueille deja.
 */
class SyncSeatingTables
{
    /**
     * Turn the groups into the capacity of each table, by table number.
     *
     * @param  array<int, array{count: int, seats: int}>  $groups
     * @return array<int, int>
     */
    public static function plan(array $groups): array
    {
        $plan = [];
        $number = 1;

        foreach ($groups as $group) {
            for ($i = 0; $i < $group['count']; $i++) {
                $plan[$number++] = $group['seats'];
            }
        }

        return $plan;
    }

    /**
     * Describe the event's current tables as groups, for the form : consecutive tables of the same
     * size make one group.
     *
     * @return array<int, array{count: int, seats: int}>
     */
    public static function groupsOf(Event $event): array
    {
        $capacities = SeatingTable::where('event_id', $event->id)->orderBy('number')->pluck('capacity');

        $groups = [];

        foreach ($capacities as $capacity) {
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['seats'] === $capacity) {
                $groups[$last]['count']++;
            } else {
                $groups[] = ['count' => 1, 'seats' => (int) $capacity];
            }
        }

        return $groups;
    }

    /**
     * List what the new plan would break for guests already seated, as displayable messages.
     *
     * @param  array<int, int>  $plan
     * @return array<int, string>
     */
    public function conflicts(Event $event, array $plan): array
    {
        $messages = [];

        foreach ($this->tables($event) as $table) {
            $seated = $table->capacity - $table->remainingCapacity();

            if ($seated === 0) {
                continue;
            }

            if (! array_key_exists($table->number, $plan)) {
                $messages[] = trans_choice('seating.errors.table_occupied', $seated, ['number' => $table->number]);
            } elseif ($plan[$table->number] < $seated) {
                $messages[] = trans_choice('seating.errors.table_too_small', $seated, ['number' => $table->number]);
            }
        }

        return $messages;
    }

    /**
     * Apply the plan : resize the existing tables, create the missing ones, remove the extra ones.
     *
     * A n'appeler qu'apres `conflicts()` : les tables retirees sont vides, les autres gardent de
     * quoi asseoir leurs occupants.
     *
     * @param  array<int, int>  $plan
     */
    public function handle(Event $event, array $plan): void
    {
        $tables = $this->tables($event)->keyBy('number');

        foreach ($plan as $number => $capacity) {
            $table = $tables->get($number);

            if ($table === null) {
                SeatingTable::create(['event_id' => $event->id, 'number' => $number, 'capacity' => $capacity]);
            } elseif ($table->capacity !== $capacity) {
                $table->update(['capacity' => $capacity]);
            }
        }

        $tables->reject(fn (SeatingTable $table) => array_key_exists($table->number, $plan))
            ->each(fn (SeatingTable $table) => $table->delete());
    }

    /**
     * @return Collection<int, SeatingTable>
     */
    private function tables(Event $event): Collection
    {
        return SeatingTable::where('event_id', $event->id)->with('assignments.registration')->get();
    }
}
