<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Reconciliation\ResolveStatementLine;
use App\Actions\Tenants\CreateTenant;
use App\Enums\ReconciliationOutcome;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\StatementImport;
use App\Models\StatementLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Resolution manuelle d'une ligne de releve (README 2.10, ecran 19), etape 9 de « Ordre de
 * construction ».
 *
 * `resolved_at` distingue « pas encore regarde » de « vu, aucune correspondance » : une ligne
 * sans inscription marquee resolue garde son issue, seul son etat de traitement change.
 */
class ResolveStatementLineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Event $event;

    private User $agent;

    private StatementLine $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->agent, 'Association Convive');

        $this->tenant->asCurrent(function () {
            $this->event = Event::factory()->open()->create();
            $import = StatementImport::factory()->create(['event_id' => $this->event->id]);
            $this->line = StatementLine::factory()->create([
                'statement_import_id' => $import->id,
                'outcome' => ReconciliationOutcome::NoRegistration,
                'amount' => 15000,
            ]);
        });
    }

    private function resolve(?Registration $registration): StatementLine
    {
        return $this->tenant->asCurrent(
            fn () => app(ResolveStatementLine::class)->handle($this->line->fresh(), $registration, $this->agent),
        );
    }

    public function test_relie_la_ligne_a_l_inscription_choisie_et_la_force_a_rapprochee(): void
    {
        $registration = $this->tenant->asCurrent(
            fn () => Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id]),
        );

        $line = $this->resolve($registration);

        $this->assertSame(ReconciliationOutcome::Matched, $line->outcome);
        $this->assertSame($registration->id, $line->matched_registration_id);
        $this->assertNotNull($line->resolved_at);
        $this->assertSame($this->agent->id, $line->resolved_by_user_id);
    }

    public function test_reprend_la_derniere_preuve_de_l_inscription(): void
    {
        [$registration, $proof] = $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id]);
            PaymentProof::factory()->create(['registration_id' => $registration->id, 'created_at' => now()->subHour()]);
            $latest = PaymentProof::factory()->create(['registration_id' => $registration->id, 'created_at' => now()]);

            return [$registration, $latest];
        });

        $line = $this->resolve($registration);

        $this->assertSame($proof->id, $line->matched_payment_proof_id);
    }

    public function test_une_inscription_sans_preuve_est_acceptee_sans_preuve_liee(): void
    {
        $registration = $this->tenant->asCurrent(
            fn () => Registration::factory()->confirmed()->create(['event_id' => $this->event->id]),
        );

        $line = $this->resolve($registration);

        $this->assertSame($registration->id, $line->matched_registration_id);
        $this->assertNull($line->matched_payment_proof_id);
    }

    public function test_sans_inscription_la_ligne_est_marquee_vue_et_garde_son_issue(): void
    {
        $line = $this->resolve(null);

        $this->assertSame(ReconciliationOutcome::NoRegistration, $line->outcome);
        $this->assertNull($line->matched_registration_id);
        $this->assertNotNull($line->resolved_at);
        $this->assertSame($this->agent->id, $line->resolved_by_user_id);
    }

    public function test_refuse_une_inscription_d_un_autre_evenement(): void
    {
        $registration = $this->tenant->asCurrent(function () {
            $otherEvent = Event::factory()->open()->create();

            return Registration::factory()->confirmed()->create(['event_id' => $otherEvent->id]);
        });

        try {
            $this->resolve($registration);
            $this->fail('Une inscription d\'un autre evenement aurait du etre refusee.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration_id', $exception->errors());
        }

        $this->assertNull($this->tenant->asCurrent(fn () => $this->line->fresh())->resolved_at);
    }

    public function test_une_resolution_est_journalisee_avec_l_avant_et_l_apres(): void
    {
        $registration = $this->tenant->asCurrent(
            fn () => Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id]),
        );

        $this->resolve($registration);

        $activity = $this->tenant->asCurrent(
            fn () => Activity::where('description', 'reconciliation.resolved')->latest('id')->first(),
        );

        $this->assertNotNull($activity);
        $this->assertSame($this->agent->id, $activity->causer_id);
        $this->assertSame(ReconciliationOutcome::NoRegistration->value, $activity->properties['old']['outcome']);
        $this->assertSame(ReconciliationOutcome::Matched->value, $activity->properties['attributes']['outcome']);
    }
}
