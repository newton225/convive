<?php

namespace Database\Factories;

use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationTableAssignment>
 */
class RegistrationTableAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'seating_table_id' => SeatingTable::factory(),
            'assigned_manually' => false,
        ];
    }
}
