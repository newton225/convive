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
            'account_number' => self::mobileNumber($channel),
            'holder_name' => fake()->name(),
            'instructions' => 'Mettez votre nom en motif du transfert.',
            'is_active' => true,
            'position' => fake()->numberBetween(0, 5),
        ];
    }

    /**
     * Indicate that the account is on the given network, with a number that looks like one.
     */
    public function onChannel(PaymentChannel $channel): static
    {
        return $this->state(fn (array $attributes) => [
            'label' => $channel->label().' principal',
            'channel' => $channel,
            'account_number' => self::mobileNumber($channel),
        ]);
    }

    /**
     * Build a credible Ivorian mobile number for the channel.
     *
     * Prefixes des operateurs en Cote d'Ivoire : Orange 07, MTN 05, Moov 01. Wave n'est pas un
     * operateur, il s'appuie sur le numero de l'abonne, quel que soit son reseau.
     */
    private static function mobileNumber(PaymentChannel $channel): string
    {
        $prefix = match ($channel) {
            PaymentChannel::MtnMoney => '05',
            PaymentChannel::MoovMoney => '01',
            default => '07',
        };

        return "+225 {$prefix} ".fake()->numerify('## ## ## ##');
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
