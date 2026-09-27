<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'status' => RegistrationStatus::Draft,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            // Reutilise une unite existante plutot que d'en fabriquer une : le nom d'une unite
            // est unique dans la base du locataire, et celles de depart occupent deja tout le
            // catalogue par defaut (voir CLAUDE.md, « Unites »).
            'unit_id' => fn () => Unit::query()->inRandomOrder()->value('id') ?? Unit::factory(),
            'amount_due' => 0,
            'party_size' => 1,
        ];
    }

    /**
     * Configure the model factory to derive the amount due and the party size from the
     * companions actually carried, once both the event and the companions are known.
     *
     * Ne s'applique que si des accompagnateurs ont ete rattaches (par exemple via
     * `->has(RegistrationCompanion::factory()->count(n))`) : sinon, un test qui pose
     * explicitement `party_size` pour eprouver le seul calcul de disponibilite verrait sa
     * valeur ecrasee par ce recalcul.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Registration $registration) {
            $companions = $registration->companions()->count();

            if ($companions === 0) {
                return;
            }

            $registration->update([
                'amount_due' => $registration->event->amountFor($companions),
                'party_size' => 1 + $companions,
            ]);
        });
    }

    /**
     * Indicate that the guest did not provide an email address (README 2.5 : facultatif).
     */
    public function withoutEmail(): static
    {
        return $this->state(fn () => ['email' => null]);
    }

    /**
     * Indicate that the registration is held, its countdown running.
     *
     * `hold_sequence` reste absent : les tests qui exercent le mecanisme de reservation
     * lui-meme (`HoldRegistration`) le posent explicitement, cet etat sert aux tests des
     * etapes suivantes (preuve, validation) qui n'ont besoin que du statut.
     */
    public function held(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::Held,
            'held_until' => now()->addMinutes(10),
        ]);
    }

    /**
     * Indicate that a payment proof has been submitted for this registration.
     */
    public function proofSubmitted(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::ProofSubmitted,
            'held_until' => now()->addMinutes(10),
        ]);
    }

    /**
     * Indicate that the registration is confirmed : its proof was validated.
     */
    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the hold expired without a proof being submitted.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::Expired,
            'held_until' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate that a submitted proof was rejected, and the guest may resubmit.
     */
    public function proofRejected(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::ProofRejected,
            'held_until' => now()->addMinutes(10),
        ]);
    }

    /**
     * Indicate that the organisation cancelled this registration (etape 9).
     */
    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => RegistrationStatus::Cancelled,
            'cancellation_reason' => 'Motif de test.',
            'cancelled_at' => now(),
        ]);
    }
}
