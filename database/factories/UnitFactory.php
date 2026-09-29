<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Jamais un nom des unites de depart (`Unit::Starters`) : l'ouverture d'un espace les cree
     * deja, et le nom est unique dans une organisation. Une unite fabriquee dans un espace ouvert
     * normalement entrait en collision avec l'une d'elles.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->unique()->lexify('Unite ?????')),
            'position' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the unit is no longer offered to guests.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
