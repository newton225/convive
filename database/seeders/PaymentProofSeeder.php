<?php

namespace Database\Seeders;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use Closure;
use Database\Factories\PaymentProofFactory;
use Illuminate\Database\Seeder;

/**
 * Preuves saines et preuves douteuses (README 2.9) : reference dupliquee, montant divergent,
 * capture deja vue. S'appuie sur l'evenement ouvert de `EventSeeder` et ses inscriptions en
 * attente de verification (`RegistrationSeeder::seedOpenEvent()`).
 */
class PaymentProofSeeder extends Seeder
{
    /**
     * Reference partagee par les deux preuves du scenario de doublon, et marqueur
     * d'idempotence (voir `alreadySeeded()`).
     */
    private const DuplicateReference = 'CI7788STV42B';

    public function run(): void
    {
        $tenant = Tenant::where('name', TenantSeeder::TenantName)->first();

        if (! $tenant) {
            return;
        }

        $tenant->run(function () {
            $event = Event::where('name', "Diner de Noel de l'Association")->first();
            $account = $event?->paymentAccounts()->first();

            if (! $event || ! $account || $this->alreadySeeded($event)) {
                return;
            }

            $this->healthyProofs($event, $account);
            $this->duplicateReference($event, $account);
            $this->amountMismatch($event, $account);
            $this->duplicateCapture($event, $account);
        });
    }

    /**
     * Determine whether this seeder already ran for this event, using its dedicated
     * reference as the marker : plus precis qu'un garde sur la table entiere, dont cette base
     * de developpement porte aussi des preuves deposees a la main en testant l'interface.
     */
    private function alreadySeeded(Event $event): bool
    {
        return PaymentProof::where('reference', self::DuplicateReference)
            ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
            ->exists();
    }

    /**
     * Une preuve saine pour chacune des inscriptions deja en attente de verification.
     */
    private function healthyProofs(Event $event, PaymentAccount $account): void
    {
        Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::ProofSubmitted)
            ->get()
            ->each(fn (Registration $registration) => $this->proof($registration, $account));
    }

    /**
     * Deux preuves portant la meme reference de transaction (README 2.9) : le meme versement
     * declare deux fois, ou deux invites qui partagent une preuve.
     */
    private function duplicateReference(Event $event, PaymentAccount $account): void
    {
        $this->proof($this->pendingRegistration($event), $account, fn ($proof) => $proof->withReference(self::DuplicateReference));
        $this->proof($this->pendingRegistration($event), $account, fn ($proof) => $proof->withReference(self::DuplicateReference));
    }

    /**
     * Un montant declare inferieur a ce que l'inscription doit (README 2.9).
     */
    private function amountMismatch(Event $event, PaymentAccount $account): void
    {
        $registration = $this->pendingRegistration($event);

        PaymentProof::factory()->create([
            'registration_id' => $registration->id,
            'payment_account_id' => $account->id,
            'amount_declared' => max(0, $registration->amount_due - 5000),
        ]);
    }

    /**
     * Deux preuves dont la capture du recu hache au meme motif (README 2.9) : la meme image
     * envoyee deux fois.
     */
    private function duplicateCapture(Event $event, PaymentAccount $account): void
    {
        $hash = str_repeat('a1', 8);

        $this->proof($this->pendingRegistration($event), $account, fn ($proof) => $proof->withPerceptualHash($hash));
        $this->proof($this->pendingRegistration($event), $account, fn ($proof) => $proof->withPerceptualHash($hash));
    }

    /**
     * @param  (Closure(PaymentProofFactory): PaymentProofFactory)|null  $state
     */
    private function proof(Registration $registration, PaymentAccount $account, ?Closure $state = null): void
    {
        $factory = $state ? $state(PaymentProof::factory()) : PaymentProof::factory();

        $factory->create([
            'registration_id' => $registration->id,
            'payment_account_id' => $account->id,
            'amount_declared' => $registration->amount_due,
        ]);
    }

    /**
     * Une inscription dediee a un scenario de preuve douteuse, distincte de celles deja
     * seminees par `RegistrationSeeder` pour ne pas dependre de leur nombre exact.
     */
    private function pendingRegistration(Event $event): Registration
    {
        return Registration::factory()->proofSubmitted()->create([
            'event_id' => $event->id,
            'amount_due' => $event->amountFor(0),
        ]);
    }
}
