<?php

namespace Database\Factories;

use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\Unit;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'status' => WaitlistStatus::Waiting,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            // Voir `RegistrationFactory` : reutilise une unite existante plutot que d'en creer
            // une, le nom d'une unite etant unique dans la base du locataire.
            'unit_id' => fn () => Unit::query()->inRandomOrder()->value('id') ?? Unit::factory(),
            'party_size' => 1,
            'companions' => [],
            'resume_token_hash' => fn () => WaitlistEntry::hashResumeToken(WaitlistEntry::generateResumeToken()),
        ];
    }

    /**
     * Indicate that this entry has been invited to finalize its registration.
     */
    public function invited(): static
    {
        return $this->state(fn () => [
            'status' => WaitlistStatus::Invited,
            'invited_at' => now(),
            'expires_at' => now()->addHours(WaitlistEntry::InviteDurationHours),
        ]);
    }

    /**
     * Indicate that the invite window has closed without a response.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => WaitlistStatus::Expired,
            'invited_at' => now()->subHours(WaitlistEntry::InviteDurationHours + 1),
            'expires_at' => now()->subHour(),
        ]);
    }
}
