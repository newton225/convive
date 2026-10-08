<?php

namespace Tests\Feature\PaymentProofs;

use App\Actions\PaymentProofs\RejectPaymentProof;
use App\Actions\Registrations\HoldRegistration;
use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RejectPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @return array{event: Event, registration: Registration, proof: PaymentProof}
     */
    private function proofSubmitted(Tenant $tenant): array
    {
        return $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id, 'amount_due' => 5000]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);

            return ['event' => $event, 'registration' => $registration, 'proof' => $proof];
        });
    }

    public function test_le_rejet_renvoie_l_inscription_vers_les_statuts_non_finalises(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['registration' => $registration, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $rejected = $tenant->asCurrent(fn () => app(RejectPaymentProof::class)->handle($proof, $owner));

        $this->assertTrue($rejected);
        $this->assertSame(RegistrationStatus::ProofRejected, $tenant->asCurrent(fn () => $registration->fresh())->status);
        $this->assertContains(RegistrationStatus::ProofRejected, Registration::UnfinalizedStatuses);
    }

    /**
     * README 2.2 : toute relance revérifie le stock. Une preuve rejetee n'est pas un
     * cul-de-sac, la meme relance que pour une reservation expiree la sort de la, sans
     * mecanisme separe a construire.
     */
    public function test_une_inscription_rejetee_peut_relancer_une_reservation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'registration' => $registration, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $tenant->asCurrent(fn () => app(RejectPaymentProof::class)->handle($proof, $owner));

        $held = $tenant->asCurrent(fn () => app(HoldRegistration::class)->handle($event, $registration->fresh()));

        $this->assertTrue($held);
        $this->assertSame(RegistrationStatus::Held, $tenant->asCurrent(fn () => $registration->fresh())->status);
    }

    public function test_refuse_de_rejeter_une_preuve_deja_validee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['registration' => $registration, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $tenant->asCurrent(fn () => $registration->update(['status' => RegistrationStatus::Confirmed]));

        $rejected = $tenant->asCurrent(fn () => app(RejectPaymentProof::class)->handle($proof, $owner));

        $this->assertFalse($rejected);
    }

    /**
     * SECURITY.md H4 : un double clic ou un rejeu reseau sur le rejet ne doit pas echouer, ni
     * journaliser deux fois.
     */
    public function test_rejeter_deux_fois_de_suite_est_sans_effet_et_idempotent(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['proof' => $proof] = $this->proofSubmitted($tenant);

        $first = $tenant->asCurrent(fn () => app(RejectPaymentProof::class)->handle($proof, $owner));
        $second = $tenant->asCurrent(fn () => app(RejectPaymentProof::class)->handle($proof, $owner));

        $this->assertTrue($first);
        $this->assertTrue($second);

        $count = $tenant->asCurrent(fn () => Activity::where('description', 'proofs.rejected')->count());

        $this->assertSame(1, $count);
    }

    public function test_le_rejet_est_journalise_avec_l_acteur(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['proof' => $proof] = $this->proofSubmitted($tenant);

        $tenant->asCurrent(fn () => app(RejectPaymentProof::class)->handle($proof, $owner));

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'proofs.rejected')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame('proof_rejected', $activity->properties['attributes']['status']);
    }
}
