<?php

namespace Database\Factories;

use App\Enums\ReconciliationOutcome;
use App\Models\StatementImport;
use App\Models\StatementLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatementLine>
 */
class StatementLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statement_import_id' => StatementImport::factory(),
            'line_number' => 1,
            'occurred_on' => now()->toDateString(),
            'reference' => strtoupper(fake()->bothify('??########')),
            'issuer' => fake()->name(),
            'amount' => fake()->numberBetween(5000, 50000),
            'outcome' => ReconciliationOutcome::NoRegistration,
        ];
    }
}
