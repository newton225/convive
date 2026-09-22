<?php

namespace Database\Factories;

use App\Enums\ScanResult;
use App\Models\Event;
use App\Models\ScanEvent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScanEvent>
 */
class ScanEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'ticket_id' => Ticket::factory(),
            'performed_by_user_id' => User::factory(),
            'result' => ScanResult::Accepted,
            'forced' => false,
        ];
    }
}
