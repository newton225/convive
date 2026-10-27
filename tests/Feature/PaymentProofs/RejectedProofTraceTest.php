<?php

namespace Tests\Feature\PaymentProofs;

use App\Actions\Registrations\PurgeRegistrations;
use App\Actions\Tenants\CreateTenant;
use App\Enums\ProofRejectionReason;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\RejectedProofTrace;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * La purge efface le dossier d'une preuve rejetee mais en garde une trace anonyme (decision du
 * 2026-10-27) : sans elle, la meme fausse reference ou la meme capture, redeposee, ne serait plus
 * reconnue.
 */
class RejectedProofTraceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    private function rejectedProof(string $reference, ?string $hash = null, ?ProofRejectionReason $reason = ProofRejectionReason::EditedCapture): PaymentProof
    {
        return $this->tenant->asCurrent(function () use ($reference, $hash, $reason) {
            $registration = Registration::factory()->create([
                'event_id' => $this->event->id,
                'status' => RegistrationStatus::ProofRejected,
                'reference' => 'CO-2026-0099',
                'name' => 'Awa Kone',
                'phone' => '+2250707123456',
            ]);

            $factory = PaymentProof::factory()->withReference($reference);

            if ($hash !== null) {
                $factory = $factory->withPerceptualHash($hash);
            }

            return $factory->create(['registration_id' => $registration->id, 'rejection_reason' => $reason]);
        });
    }

    private function purge(): void
    {
        $this->tenant->asCurrent(fn () => app(PurgeRegistrations::class)->handle($this->event));
    }

    public function test_la_purge_garde_une_trace_sans_donnee_personnelle_d_une_preuve_rejetee(): void
    {
        $this->rejectedProof('CI2410 ABC-123', 'ab12cd34ef56ab12');

        $this->purge();

        $this->tenant->asCurrent(function () {
            $this->assertSame(0, PaymentProof::count());
            $this->assertSame(0, Registration::count());

            $trace = RejectedProofTrace::firstOrFail();

            $this->assertSame($this->event->id, $trace->event_id);
            $this->assertSame('CI2410 ABC-123', $trace->reference);
            $this->assertSame('CI2410ABC123', $trace->reference_key);
            $this->assertSame('ab12cd34ef56ab12', $trace->perceptual_hash);
            $this->assertSame(ProofRejectionReason::EditedCapture, $trace->rejection_reason);

            $columns = array_keys($trace->getAttributes());
            $this->assertEqualsCanonicalizing(
                ['id', 'event_id', 'reference', 'reference_key', 'perceptual_hash', 'rejection_reason', 'submitted_at', 'created_at'],
                $columns,
            );
        });
    }

    public function test_une_preuve_jamais_rejetee_ne_laisse_aucune_trace(): void
    {
        $this->rejectedProof('T_JAMAIS_REJETEE', reason: null);

        $this->purge();

        $this->tenant->asCurrent(fn () => $this->assertSame(0, RejectedProofTrace::count()));
    }

    public function test_une_reference_deja_rejetee_est_reconnue_apres_la_purge_meme_ecrite_autrement(): void
    {
        $this->rejectedProof('CI2410 ABC-123');
        $this->purge();

        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id]);
            $proof = PaymentProof::factory()->withReference('ci2410abc123')->create(['registration_id' => $registration->id]);

            $this->assertTrue($proof->hasDuplicateReference());
            $this->assertCount(1, $proof->duplicateReferenceTraces());
        });
    }

    public function test_une_capture_deja_rejetee_est_reconnue_apres_la_purge(): void
    {
        $this->rejectedProof('T_AVANT', 'ab12cd34ef56ab12');
        $this->purge();

        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id]);
            // Un bit d'ecart : toujours la meme capture, a la tolerance pres.
            $proof = PaymentProof::factory()->withReference('T_APRES')->withPerceptualHash('ab12cd34ef56ab13')->create(['registration_id' => $registration->id]);

            $this->assertTrue($proof->hasDuplicateImage());
            $this->assertCount(1, $proof->duplicateImageTraces());
        });
    }

    public function test_une_reference_inedite_n_est_pas_signalee_par_une_trace(): void
    {
        $this->rejectedProof('T_ANCIENNE');
        $this->purge();

        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id]);
            $proof = PaymentProof::factory()->withReference('T_NOUVELLE')->create(['registration_id' => $registration->id]);

            $this->assertFalse($proof->hasDuplicateReference());
            $this->assertCount(0, $proof->duplicateReferenceTraces());
        });
    }

    public function test_la_file_de_preuves_montre_la_trace_sans_nom_ni_capture(): void
    {
        $this->rejectedProof('T_REUTILISEE');
        $this->purge();

        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id]);
            PaymentProof::factory()->withReference('T_REUTILISEE')->create(['registration_id' => $registration->id]);
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.proofs.index', [$this->tenant, $this->event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.signals.duplicateReference', true)
                ->has('rows.0.duplicateReferenceMatches', 1)
                ->where('rows.0.duplicateReferenceMatches.0.purged', true)
                ->where('rows.0.duplicateReferenceMatches.0.registrationReference', null)
                ->where('rows.0.duplicateReferenceMatches.0.receiptUrl', null)
                ->where('rows.0.duplicateReferenceMatches.0.status', 'proof_rejected')
                ->where('rows.0.duplicateReferenceMatches.0.reference', 'T_REUTILISEE')
                ->where('rows.0.duplicateReferenceMatches.0.sameRegistration', false),
            );
    }

    public function test_la_trace_disparait_avec_l_evenement(): void
    {
        $this->rejectedProof('T_EFFACEE');
        $this->purge();

        $this->tenant->asCurrent(function () {
            $this->assertSame(1, RejectedProofTrace::count());

            $this->event->delete();

            $this->assertSame(0, RejectedProofTrace::count());
        });
    }

    public function test_une_purge_complete_garde_les_references_au_journal_sans_nom_ni_numero(): void
    {
        $this->rejectedProof('T_JOURNAL');

        $this->purge();

        $activity = Activity::where('description', 'registrations.purged')->latest('id')->firstOrFail();

        $this->assertSame(['CO-2026-0099'], $activity->properties['references']);
        $this->assertStringNotContainsString('Awa Kone', (string) json_encode($activity->properties));
        $this->assertStringNotContainsString('+2250707123456', (string) json_encode($activity->properties));
    }
}
