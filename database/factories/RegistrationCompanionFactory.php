<?php

namespace Database\Factories;

use App\Models\Registration;
use App\Models\RegistrationCompanion;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationCompanion>
 */
class RegistrationCompanionFactory extends Factory
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
            'name' => fake()->name(),
            // Voir `RegistrationFactory` : reutilise une unite existante plutot que d'en creer
            // une, le nom d'une unite etant unique dans la base du locataire.
            'unit_id' => fn () => Unit::query()->inRandomOrder()->value('id') ?? Unit::factory(),
            'position' => 0,
        ];
    }
}
