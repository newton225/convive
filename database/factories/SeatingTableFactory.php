<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\SeatingTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatingTable>
 */
class SeatingTableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            // Au-dessus des tables que la fabrique d'evenement pose d'elle-meme (1 a 20) : une table
            // ajoutee a la main ne heurte jamais leur numero.
            'number' => fake()->unique()->numberBetween(201, 400),
            'capacity' => 10,
        ];
    }

    /**
     * Indicate that this table is reserved for the given unit.
     */
    public function reservedFor(int $unitId): static
    {
        return $this->state(fn () => ['reserved_unit_id' => $unitId]);
    }
}
