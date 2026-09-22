<?php

namespace Tests\Feature\PaymentProofs;

use App\Actions\PaymentProofs\ValidatePaymentProof;
use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Registrations\InvitationCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ValidatePaymentProofTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @return array{registration: Registration, proof: PaymentProof}
     */
    private function proofSubmitted(Tenant $tenant): array
    {
        return $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);

            return ['registration' => $registration, 'proof' => $proof];
        });
    }

    public function test_la_validation_confirme_l_inscription(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['registration' => $registration, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $validated = $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));

        $this->assertTrue($validated);
        $this->assertSame(RegistrationStatus::Confirmed, $tenant->asCurrent(fn () => $registration->fresh())->status);
    }

    public function test_refuse_de_valider_une_preuve_qui_n_est_plus_en_attente(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['registration' => $registration, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $tenant->asCurrent(fn () => $registration->update(['status' => RegistrationStatus::ProofRejected]));

        $validated = $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));

        $this->assertFalse($validated);
    }

    /**
     * SECURITY.md H4 : un double clic ou un rejeu reseau sur la validation ne doit pas
     * echouer, ni journaliser deux fois.
     */
    public function test_valider_deux_fois_de_suite_est_sans_effet_et_idempotent(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['proof' => $proof] = $this->proofSubmitted($tenant);

        $first = $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));
        $second = $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));

        $this->assertTrue($first);
        $this->assertTrue($second);

        $count = $tenant->asCurrent(fn () => Activity::where('description', 'proofs.validated')->count());

        $this->assertSame(1, $count);
    }

    /**
     * README 2.6 : l'attribution des tables est automatique, a la validation.
     */
    public function test_la_validation_declenche_l_attribution_d_une_table(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['registration' => $registration, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));

        $this->assertNotNull($tenant->asCurrent(fn () => $registration->fresh()->tableAssignment));
    }

    /**
     * README ecran 24, regle « attribuer les tables automatiquement a la validation ».
     */
    public function test_l_attribution_automatique_n_a_pas_lieu_quand_l_evenement_la_desactive(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        ['registration' => $registration, 'proof' => $proof] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create(['rule_auto_seating' => false]);
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);

            return ['registration' => $registration, 'proof' => $proof];
        });

        $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));

        $this->assertSame(
            RegistrationStatus::Confirmed,
            $tenant->asCurrent(fn () => $registration->fresh())->status,
        );
        $this->assertNull($tenant->asCurrent(fn () => $registration->fresh()->tableAssignment));
    }

    /**
     * README ecran 24, regle « envoyer les cartes a l'echeance programmee ».
     */
    public function test_la_carte_n_est_pas_envoyee_quand_l_evenement_desactive_l_envoi_programme(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $tenant->update(['subdomain' => 'convive-ci']);

        ['registration' => $registration, 'proof' => $proof] = $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create([
                'invitations_send_at' => now()->subMinute(),
                'rule_scheduled_send' => false,
            ]);
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);

            return ['registration' => $registration, 'proof' => $proof];
        });

        $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));

        Notification::assertNothingSent();
        $this->assertNull($tenant->asCurrent(fn () => $registration->fresh())->card_sent_at);
    }

    /**
     * README 2.7 : « une inscription validee apres l'echeance est envoyee a la validation ».
     */
    public function test_la_validation_envoie_la_carte_quand_l_echeance_est_deja_passee(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        // `Registration::signedResumeUrl()` a besoin d'un sous-domaine pour construire le lien
        // de la carte, comme `Event::publicUrl()`.
        $tenant->update(['subdomain' => 'convive-ci']);

        ['registration' => $registration, 'proof' => $proof] = $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create(['invitations_send_at' => now()->subMinute()]);
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);

            return ['registration' => $registration, 'proof' => $proof];
        });

        $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));

        Notification::assertSentOnDemandTimes(InvitationCard::class, 1);
        $this->assertNotNull($tenant->asCurrent(fn () => $registration->fresh())->card_sent_at);
    }

    public function test_la_validation_n_envoie_pas_la_carte_avant_l_echeance(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['proof' => $proof] = $this->proofSubmitted($tenant);

        $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));

        Notification::assertNothingSent();
    }

    public function test_la_validation_est_journalisee_avec_l_acteur(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['proof' => $proof] = $this->proofSubmitted($tenant);

        $tenant->asCurrent(fn () => app(ValidatePaymentProof::class)->handle($proof, $owner));

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'proofs.validated')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame('confirmed', $activity->properties['attributes']['status']);
    }
}
