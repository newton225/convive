<?php

namespace Database\Factories;

use App\Enums\ClaimCategory;
use App\Enums\ClaimStatus;
use App\Models\GuestClaim;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestClaim>
 */
class GuestClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'category' => ClaimCategory::Payment,
            'message' => fake()->sentence(12),
            'status' => ClaimStatus::Open,
            'resolved_at' => null,
            'resolved_by_user_id' => null,
        ];
    }

    /**
     * Indicate that the claim has been handled.
     */
    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => ClaimStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
