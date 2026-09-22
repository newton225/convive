<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\StatementImport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StatementImport>
 */
class StatementImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            // Pas `User::factory()` : chaque utilisateur cree une organisation personnelle, ce qui
            // echouerait sous la tenancy d'un autre locataire. `User` vit dans la base centrale.
            'imported_by_user_id' => fake()->numberBetween(1, 1000),
            'original_filename' => 'releve.csv',
            'content_hash' => hash('sha256', (string) Str::uuid()),
            'row_count' => 1,
        ];
    }
}
