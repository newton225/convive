<?php

namespace Database\Factories;

use App\Enums\PaymentChannel;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentProof>
 */
class PaymentProofFactory extends Factory
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
            'payment_account_id' => PaymentAccount::factory(),
            'channel' => PaymentChannel::Wave,
            'reference' => strtoupper(fake()->bothify('??########')),
            'guest_note' => null,
            'perceptual_hash' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    /**
     * Indicate that the proof carries the given transaction reference.
     */
    public function withReference(string $reference): static
    {
        return $this->state(fn () => ['reference' => $reference]);
    }

    /**
     * Indicate that the proof's receipt hashes to the given perceptual hash.
     */
    public function withPerceptualHash(string $hash): static
    {
        return $this->state(fn () => ['perceptual_hash' => $hash]);
    }
}
