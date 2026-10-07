<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventPriceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPriceCategory>
 */
class EventPriceCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->unique()->randomElement(['Standard', 'VIP', 'VVIP', 'Etudiant', 'Couple', 'Enfant']),
            'price' => 15000,
            'quota' => null,
            'position' => 0,
        ];
    }
}
