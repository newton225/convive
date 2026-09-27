<?php

namespace Database\Factories;

use App\Models\ShowcaseEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShowcaseEvent>
 */
class ShowcaseEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'event_id' => fake()->numberBetween(1, 1000),
            'name' => 'Diner '.fake()->unique()->word(),
            'organisation_name' => fake()->company(),
            'starts_at' => fake()->dateTimeBetween('+2 weeks', '+3 months'),
            'public_url' => 'https://'.fake()->domainWord().'.convive.test/e/'.bin2hex(random_bytes(32)),
            'announced_at' => now(),
        ];
    }
}
