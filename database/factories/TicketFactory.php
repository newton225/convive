<?php

namespace Database\Factories;

use App\Models\Registration;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory()->confirmed(),
            'holder_position' => Ticket::GuestPosition,
            'nonce' => Ticket::generateNonce(),
            'key_version' => 1,
            'issued_at' => now(),
        ];
    }
}
