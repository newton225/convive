<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ReconciliationOutcome;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\StatementImport;
use App\Models\StatementLine;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Ecran 19, rapprochement du releve, etape 9 de « Ordre de construction ».
 */
class ReconciliationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    private function eventOf(Tenant $tenant): Event
    {
        return $tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    private function csv(string $content = "date,reference,emetteur,montant\n2026-09-20,WV0001,Aya Kouassi,15000", string $name = 'releve.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /**
     * @param  array<int, ReconciliationOutcome>  $outcomes
     */
    private function importWithLines(Tenant $tenant, Event $event, array $outcomes): StatementImport
    {
        return $tenant->asCurrent(function () use ($event, $outcomes) {
            $import = StatementImport::factory()->create(['event_id' => $event->id, 'row_count' => count($outcomes)]);

            foreach ($outcomes as $index => $outcome) {
                StatementLine::factory()->create([
                    'statement_import_id' => $import->id,
                    'line_number' => $index + 1,
                    'outcome' => $outcome,
                ]);
            }

            return $import;
        });
    }

    public function test_un_membre_avec_la_permission_voit_les_lignes_du_dernier_import(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->importWithLines($tenant, $event, [ReconciliationOutcome::NoRegistration]);
        $latest = $this->importWithLines($tenant, $event, [ReconciliationOutcome::Matched, ReconciliationOutcome::Matched]);

        $this->actingAs($owner)
            ->get(route('tenants.events.reconciliation.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/reconciliation')
                ->where('currentImportId', $latest->id)
                ->has('rows', 2)
                ->has('imports', 2),
            );
    }

    public function test_le_selecteur_d_import_affiche_l_import_demande(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $first = $this->importWithLines($tenant, $event, [ReconciliationOutcome::NoRegistration]);
        $this->importWithLines($tenant, $event, [ReconciliationOutcome::Matched, ReconciliationOutcome::Matched]);

        $this->actingAs($owner)
            ->get(route('tenants.events.reconciliation.index', [$tenant, $event]).'?import='.$first->id)
            ->assertInertia(fn ($page) => $page->where('currentImportId', $first->id)->has('rows', 1));
    }

    public function test_un_import_d_un_autre_evenement_ne_s_affiche_pas(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $otherEvent = $this->eventOf($tenant);

        $foreign = $this->importWithLines($tenant, $otherEvent, [ReconciliationOutcome::Matched]);

        $this->actingAs($owner)
            ->get(route('tenants.events.reconciliation.index', [$tenant, $event]).'?import='.$foreign->id)
            ->assertNotFound();
    }

    public function test_les_statistiques_comptent_chaque_issue(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->importWithLines($tenant, $event, [
            ReconciliationOutcome::Matched,
            ReconciliationOutcome::Matched,
            ReconciliationOutcome::AmountMismatch,
            ReconciliationOutcome::ApproximateName,
            ReconciliationOutcome::NoRegistration,
        ]);

        $this->actingAs($owner)
            ->get(route('tenants.events.reconciliation.index', [$tenant, $event]))
            ->assertInertia(fn ($page) => $page
                ->where('stats.matched', 2)
                ->where('stats.amountMismatch', 1)
                ->where('stats.approximateName', 1)
                ->where('stats.noRegistration', 1),
            );
    }

    public function test_sans_import_l_ecran_s_affiche_avec_un_etat_vide(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->get(route('tenants.events.reconciliation.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('currentImportId', null)->has('rows', 0)->has('imports', 0));
    }

    public function test_un_membre_sans_la_permission_ne_voit_pas_le_rapprochement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.reconciliation.index', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_rapprochement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.reconciliation.index', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_un_membre_avec_la_permission_importe_un_releve(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.reconciliation.import', [$tenant, $event]), ['file' => $this->csv()])
            ->assertRedirect(route('tenants.events.reconciliation.index', [$tenant, $event]));

        $this->assertSame(1, $tenant->asCurrent(fn () => StatementImport::where('event_id', $event->id)->count()));
    }

    public function test_l_import_exige_la_permission_d_import(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ReconciliationResolve]);

        $this->actingAs($member)
            ->post(route('tenants.events.reconciliation.import', [$tenant, $event]), ['file' => $this->csv()])
            ->assertForbidden();

        $this->assertSame(0, $tenant->asCurrent(fn () => StatementImport::count()));
    }

    public function test_un_locataire_tiers_recoit_404_a_l_import(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->post(route('tenants.events.reconciliation.import', [$tenant, $event]), ['file' => $this->csv()])
            ->assertNotFound();
    }

    public function test_le_fichier_est_obligatoire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.reconciliation.import', [$tenant, $event]), [])
            ->assertSessionHasErrors('file');
    }

    public function test_un_fichier_qui_n_est_pas_un_csv_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.reconciliation.import', [$tenant, $event]), [
                'file' => UploadedFile::fake()->image('releve.png'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_un_releve_mal_forme_est_rejete_avec_un_message_sur_le_champ_fichier(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.reconciliation.import', [$tenant, $event]), [
                'file' => $this->csv("date,reference,emetteur,montant\n2026-09-20,WV0001,Aya Kouassi,abc"),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, $tenant->asCurrent(fn () => StatementImport::count()));
    }

    public function test_l_import_est_limite_en_debit(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        for ($i = 0; $i < AppServiceProvider::ExportsPerHour; $i++) {
            $this->actingAs($owner)
                ->post(route('tenants.events.reconciliation.import', [$tenant, $event]), [
                    'file' => $this->csv("date,reference,emetteur,montant\n2026-09-20,WV000{$i},Aya Kouassi,15000"),
                ])
                ->assertRedirect();
        }

        $this->actingAs($owner)
            ->post(route('tenants.events.reconciliation.import', [$tenant, $event]), [
                'file' => $this->csv("date,reference,emetteur,montant\n2026-09-20,WV0009,Aya Kouassi,15000"),
            ])
            ->assertTooManyRequests();
    }

    public function test_un_membre_avec_la_permission_resout_une_ligne_en_choisissant_une_inscription(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $import = $this->importWithLines($tenant, $event, [ReconciliationOutcome::NoRegistration]);
        $line = $tenant->asCurrent(fn () => StatementLine::where('statement_import_id', $import->id)->firstOrFail());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ReconciliationResolve, TenantPermission::ReconciliationImport]);

        $this->actingAs($member)
            ->post(route('tenants.events.reconciliation.resolve', [$tenant, $event, $line]), ['registration_id' => $registration->id])
            ->assertRedirect();

        $fresh = $tenant->asCurrent(fn () => $line->fresh());

        $this->assertSame(ReconciliationOutcome::Matched, $fresh->outcome);
        $this->assertSame($registration->id, $fresh->matched_registration_id);
    }

    public function test_un_membre_peut_marquer_une_ligne_comme_sans_inscription(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $import = $this->importWithLines($tenant, $event, [ReconciliationOutcome::NoRegistration]);
        $line = $tenant->asCurrent(fn () => StatementLine::where('statement_import_id', $import->id)->firstOrFail());

        $this->actingAs($owner)
            ->post(route('tenants.events.reconciliation.resolve', [$tenant, $event, $line]), ['registration_id' => null])
            ->assertRedirect();

        $this->assertNotNull($tenant->asCurrent(fn () => $line->fresh())->resolved_at);
    }

    public function test_la_resolution_exige_la_permission_de_resolution(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $import = $this->importWithLines($tenant, $event, [ReconciliationOutcome::NoRegistration]);
        $line = $tenant->asCurrent(fn () => StatementLine::where('statement_import_id', $import->id)->firstOrFail());

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ReconciliationImport]);

        $this->actingAs($member)
            ->post(route('tenants.events.reconciliation.resolve', [$tenant, $event, $line]), ['registration_id' => null])
            ->assertForbidden();

        $this->assertNull($tenant->asCurrent(fn () => $line->fresh())->resolved_at);
    }

    public function test_une_inscription_d_un_autre_evenement_est_refusee_a_la_resolution(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $otherEvent = $this->eventOf($tenant);
        $import = $this->importWithLines($tenant, $event, [ReconciliationOutcome::NoRegistration]);
        $line = $tenant->asCurrent(fn () => StatementLine::where('statement_import_id', $import->id)->firstOrFail());
        $foreign = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $otherEvent->id]));

        $this->actingAs($owner)
            ->post(route('tenants.events.reconciliation.resolve', [$tenant, $event, $line]), ['registration_id' => $foreign->id])
            ->assertSessionHasErrors('registration_id');

        $this->assertNull($tenant->asCurrent(fn () => $line->fresh())->resolved_at);
    }

    public function test_une_ligne_d_un_autre_evenement_recoit_404_a_la_resolution(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $otherEvent = $this->eventOf($tenant);
        $import = $this->importWithLines($tenant, $otherEvent, [ReconciliationOutcome::NoRegistration]);
        $line = $tenant->asCurrent(fn () => StatementLine::where('statement_import_id', $import->id)->firstOrFail());

        $this->actingAs($owner)
            ->post(route('tenants.events.reconciliation.resolve', [$tenant, $event, $line]), ['registration_id' => null])
            ->assertNotFound();
    }

    public function test_un_locataire_tiers_recoit_404_a_la_resolution(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $import = $this->importWithLines($tenant, $event, [ReconciliationOutcome::NoRegistration]);
        $line = $tenant->asCurrent(fn () => StatementLine::where('statement_import_id', $import->id)->firstOrFail());

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->post(route('tenants.events.reconciliation.resolve', [$tenant, $event, $line]), ['registration_id' => null])
            ->assertNotFound();
    }
}
