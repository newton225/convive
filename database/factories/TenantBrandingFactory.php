<?php

namespace Database\Factories;

use App\Enums\LegalForm;
use App\Models\Tenant;
use App\Models\TenantBranding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantBranding>
 */
class TenantBrandingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'display_name' => fake()->company(),
            'legal_name' => fake()->company(),
            'legal_form' => fake()->randomElement(LegalForm::cases()),
            'representative_name' => fake()->name(),
            'registration_number' => 'CI-ABJ-'.fake()->numberBetween(2015, 2025).'-B-'.fake()->numerify('#####'),
            'tax_number' => fake()->numerify('#######').' '.fake()->randomLetter(),
            'address' => fake()->streetAddress(),
            'city' => 'Abidjan',
            'country' => 'CI',
            'email' => fake()->companyEmail(),
            'phone' => '+225 07 '.fake()->numerify('## ## ## ##'),
            'primary_color' => TenantBranding::DefaultPrimaryColor,
            'secondary_color' => TenantBranding::DefaultSecondaryColor,
        ];
    }

    /**
     * Indicate that the legal identity is not complete enough to publish.
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_number' => null,
            'registration_number' => null,
        ]);
    }
}
