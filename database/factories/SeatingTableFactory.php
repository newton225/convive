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
            'number' => fake()->unique()->numberBetween(1, 200),
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
