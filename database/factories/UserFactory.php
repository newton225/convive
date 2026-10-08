<?php

namespace Database\Factories;

use App\Actions\Tenants\CreateTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Secret TOTP valide (base32) porte par `withTwoFactor()`, publique pour que les tests qui
     * doivent soumettre un code reel (rejeu du second facteur, SECURITY.md C1) puissent le
     * calculer avec le meme moteur que l'application (`Google2FA::getCurrentOtp()`), plutot
     * qu'une chaine arbitraire qu'aucun generateur n'accepterait.
     */
    public const TwoFactorSecret = 'R23UBO7TJJLL72RL';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($user) {
            app(CreateTenant::class)->handle(
                $user,
                "Organisation de {$user->name}",
                isPersonal: true,
            );
        });
    }

    /**
     * Indicate that the account belongs to no organisation, like one created from an invitation
     * (decision du 2026-10-07 : aucune organisation personnelle n'est ouverte dans ce cas).
     */
    public function withoutOrganisation(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->tenants()->detach();
            $user->forceFill(['current_tenant_id' => null])->save();
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt(self::TwoFactorSecret),
            'two_factor_recovery_codes' => encrypt(json_encode([hash('sha256', 'recovery-code-1')])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
