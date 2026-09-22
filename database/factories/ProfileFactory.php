<?php

namespace Database\Factories;

use App\Enums\TenantPermission;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
            'guard_name' => 'web',
            'is_system' => false,
        ];
    }

    /**
     * Indicate that the profile is the tenant's system owner profile.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Profile::Owner,
            'is_system' => true,
        ]);
    }

    /**
     * Give the profile the whole permission catalogue.
     */
    public function withEveryPermission(): static
    {
        return $this->afterCreating(
            fn (Profile $profile) => $profile->syncPermissions(TenantPermission::values()),
        );
    }
}
