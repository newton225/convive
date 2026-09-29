<?php

namespace Tests\Feature\PaymentProofs;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\StatementImport;
use App\Models\StatementLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les deux derniers signaux de preuve douteuse de README 2.9, possibles depuis qu'un releve peut
 * etre importe (etape 9) : reference absente du releve, montant du releve different du montant
 * du de l'inscription (l'invite ne declare plus de montant, decision du 2026-09-29).
 *
 * Sans releve importe pour l'evenement, aucun des deux signaux ne s'allume : l'absence d'un
 * releve n'est pas la preuve qu'une reference manque.
 */
class StatementSignalsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    private function proof(?string $reference, int $amountDue, ?Event $event = null): PaymentProof
    {
        $registration = Registration::factory()->proofSubmitted()->create([
            'event_id' => ($event ?? $this->event)->id,
            'amount_due' => $amountDue,
        ]);

        return PaymentProof::factory()->create([
            'registration_id' => $registration->id,
            'reference' => $reference,
        ]);
    }

    private function statementLine(string $reference, int $amount, ?Event $event = null): void
    {
        $import = StatementImport::factory()->create(['event_id' => ($event ?? $this->event)->id]);

        StatementLine::factory()->create([
            'statement_import_id' => $import->id,
            'reference' => $reference,
            'amount' => $amount,
        ]);
    }

    public function test_sans_releve_importe_aucun_signal_ne_s_allume(): void
    {
        $this->tenant->asCurrent(function () {
            $proof = $this->proof('WV0001', 15000);

            $this->assertFalse($proof->referenceMissingFromStatement());
            $this->assertFalse($proof->hasStatementAmountMismatch());
        });
    }

    public function test_une_reference_absente_du_releve_allume_le_signal(): void
    {
        $this->tenant->asCurrent(function () {
            $this->statementLine('WV9999', 15000);
            $proof = $this->proof('WV0001', 15000);

            $this->assertTrue($proof->referenceMissingFromStatement());
        });
    }

    public function test_une_reference_presente_au_releve_n_allume_pas_le_signal(): void
    {
        $this->tenant->asCurrent(function () {
            $this->statementLine('wv0001', 15000);
            $proof = $this->proof('WV0001', 15000);

            $this->assertFalse($proof->referenceMissingFromStatement());
        });
    }

    public function test_un_versement_en_especes_sans_reference_n_allume_pas_le_signal(): void
    {
        $this->tenant->asCurrent(function () {
            $this->statementLine('WV9999', 15000);
            $proof = $this->proof(null, 15000);

            $this->assertFalse($proof->referenceMissingFromStatement());
            $this->assertFalse($proof->hasStatementAmountMismatch());
        });
    }

    public function test_un_montant_de_releve_different_du_montant_du_allume_le_signal(): void
    {
        $this->tenant->asCurrent(function () {
            $this->statementLine('WV0001', 9000);
            $proof = $this->proof('WV0001', 15000);

            $this->assertTrue($proof->hasStatementAmountMismatch());
        });
    }

    public function test_un_montant_de_releve_egal_au_montant_du_n_allume_pas_le_signal(): void
    {
        $this->tenant->asCurrent(function () {
            $this->statementLine('WV0001', 15000);
            $proof = $this->proof('WV0001', 15000);

            $this->assertFalse($proof->hasStatementAmountMismatch());
        });
    }

    public function test_un_releve_d_un_autre_evenement_ne_compte_pas(): void
    {
        $this->tenant->asCurrent(function () {
            $otherEvent = Event::factory()->open()->create();
            $this->statementLine('WV9999', 15000, $otherEvent);

            $proof = $this->proof('WV0001', 15000);

            // L'evenement de la preuve n'a aucun releve : pas de signal, meme si un autre en a un.
            $this->assertFalse($proof->referenceMissingFromStatement());
        });
    }

    public function test_les_signaux_sont_exposes_dans_la_file_de_preuves(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Autre Association');
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());

        $tenant->asCurrent(function () use ($event) {
            $import = StatementImport::factory()->create(['event_id' => $event->id]);
            StatementLine::factory()->create(['statement_import_id' => $import->id, 'reference' => 'WV9999', 'amount' => 1000]);

            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id, 'amount_due' => 15000]);
            PaymentProof::factory()->create([
                'registration_id' => $registration->id,
                'reference' => 'WV0001',
            ]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.signals.referenceMissingFromStatement', true)
                ->where('rows.0.signals.statementAmountMismatch', false)
                ->where('rows.0.signals.guestNote', false),
            );
    }

    public function test_une_precision_laissee_par_l_invite_allume_un_signal_et_se_lit_dans_la_file(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Autre Association');
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());

        $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            PaymentProof::factory()->create([
                'registration_id' => $registration->id,
                'guest_note' => 'Envoye par ma soeur, depuis son numero.',
            ]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.signals.guestNote', true)
                ->where('rows.0.guestNote', 'Envoye par ma soeur, depuis son numero.'),
            );
    }
}
