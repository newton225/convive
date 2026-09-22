<?php

namespace Database\Factories;

use App\Actions\Tenants\CreateStarterProfiles;
use App\Actions\Tenants\CreateStarterUnits;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Pas de `slug` ici : c'est `Tenant::creating` qui le fabrique et garantit son
        // unicite. Deux raisons sociales distinctes peuvent donner le meme slug, et une
        // fabrique qui court-circuite cette regle produit des enregistrements que
        // l'application ne cree jamais.
        return [
            'name' => fake()->unique()->company(),
            'is_personal' => false,
        ];
    }

    /**
     * Configure the model factory.
     *
     * Un locataire possede toujours ses profils et ses unites de depart : la fabrique
     * reproduit cette realite plutot que de laisser des tests travailler sur un espace
     * impossible.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            $tenant->run(function () {
                app(CreateStarterProfiles::class)->handle();
                app(CreateStarterUnits::class)->handle();
            });
        });
    }

    /**
     * Indicate that the tenant is a personal tenant.
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_personal' => true,
        ]);
    }

    /**
     * Indicate that the tenant has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
