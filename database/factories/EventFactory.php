<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+2 weeks', '+3 months');

        return [
            'name' => 'Diner '.fake()->unique()->word().' '.fake()->word(),
            'subtitle' => fake()->sentence(4),
            'status' => EventStatus::Draft,
            'starts_at' => $startsAt,
            'venue' => 'Hotel Ivoire',
            'venue_address' => 'Boulevard Hassan II, Cocody, Abidjan',
            'table_count' => 20,
            'seats_per_table' => 10,
            'price_per_person' => 15000,
            'companion_limit' => Event::MaximumCompanionLimit,
            'registration_deadline' => (clone $startsAt)->modify('-3 days'),
            'purge_at' => (clone $startsAt)->modify('-2 days'),
            'invitations_send_at' => (clone $startsAt)->modify('-7 days'),
            'hold_duration_minutes' => Event::DefaultHoldDurationMinutes,
        ];
    }

    /**
     * Indicate that the event is open to registrations.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Open]);
    }

    /**
     * Indicate that a public link has been handed out.
     */
    public function published(): static
    {
        return $this->open()->state(fn (array $attributes) => [
            'public_token' => bin2hex(random_bytes(Event::PublicTokenBytes)),
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the event is over.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Closed]);
    }
}
