<?php

namespace Database\Factories;

use App\Enums\PaymentChannel;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Par defaut, le compte est deja actif et visible : c'est l'etat d'un compte dont le delai
     * d'activation est passe.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $channel = fake()->randomElement([PaymentChannel::Wave, PaymentChannel::OrangeMoney, PaymentChannel::MtnMoney]);

        return [
            'label' => $channel->label().' principal',
            'channel' => $channel,
            'account_number' => '+225 07 '.fake()->numerify('## ## ## ##'),
            'holder_name' => fake()->name(),
            'instructions' => 'Mettez votre nom en motif du transfert.',
            'is_active' => true,
            'position' => fake()->numberBetween(0, 5),
        ];
    }

    /**
     * Indicate that a change is waiting for the activation delay to pass.
     */
    public function withPendingChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'pending_channel' => PaymentChannel::Wave,
            'pending_account_number' => '+225 05 '.fake()->numerify('## ## ## ##'),
            'pending_holder_name' => fake()->name(),
            'pending_activates_at' => now()->addHours(PaymentAccount::ActivationDelayHours),
        ]);
    }

    /**
     * Indicate that the account has never been activated: it exists but is shown nowhere.
     */
    public function neverActivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => null,
            'account_number' => null,
            'holder_name' => null,
        ])->withPendingChange();
    }
}
