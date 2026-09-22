<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketArrival;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketArrival>
 */
class TicketArrivalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'performed_by_user_id' => User::factory(),
        ];
    }
}
