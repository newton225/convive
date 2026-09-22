<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

/**
 * Export PDF de la base d'inscrits et listes de controle par table (README ecran 20, ecran 15),
 * etape 9 de « Ordre de construction ».
 *
 * `Pdf::fake()` ne rend pas le PDF : les assertions portent sur le nom de la vue et sur les
 * donnees qui lui sont passees (`viewName`, `viewData`), pas sur le contenu binaire.
 */
class RegistrationPdfExportTest extends TestCase
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

    /**
     * @return array<int, string>
     */
    private function namesSentToView(PdfBuilder $pdf): array
    {
        return collect($pdf->viewData['registrations'])->pluck('name')->all();
    }

    public function test_l_export_pdf_de_la_base_d_inscrits_rend_la_vue_dediee(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'name' => 'Aya Kouassi']));

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.pdf', [$tenant, $event]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'pdf.registrations'
            && $this->namesSentToView($pdf) === ['Aya Kouassi']);
    }

    public function test_l_export_pdf_reprend_le_filtre_actif_de_l_ecran(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $tenant->asCurrent(function () use ($event) {
            Registration::factory()->confirmed()->create(['event_id' => $event->id, 'name' => 'Aya Kouassi']);
            Registration::factory()->cancelled()->create(['event_id' => $event->id, 'name' => 'Kofi Diallo']);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.pdf', [$tenant, $event]).'?filter[status]=cancelled')
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $this->namesSentToView($pdf) === ['Kofi Diallo']);
    }

    public function test_l_export_pdf_exige_la_permission_d_export(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.registrations.export.pdf', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_l_export_pdf(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.registrations.export.pdf', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_les_listes_de_controle_regroupent_les_confirmes_par_table_dans_l_ordre(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $tenant->asCurrent(function () use ($event) {
            $tableTwo = SeatingTable::factory()->create(['event_id' => $event->id, 'number' => 2]);
            $tableOne = SeatingTable::factory()->create(['event_id' => $event->id, 'number' => 1]);

            $aya = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'name' => 'Aya Kouassi']);
            $kofi = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'name' => 'Kofi Diallo']);

            RegistrationTableAssignment::factory()->create(['registration_id' => $aya->id, 'seating_table_id' => $tableTwo->id]);
            RegistrationTableAssignment::factory()->create(['registration_id' => $kofi->id, 'seating_table_id' => $tableOne->id]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.checklists', [$tenant, $event]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
            if ($pdf->viewName !== 'pdf.checklists') {
                return false;
            }

            $tables = collect($pdf->viewData['tables']);

            return $tables->pluck('number')->all() === [1, 2]
                && $tables->first()['registrations'][0]['name'] === 'Kofi Diallo'
                && $tables->last()['registrations'][0]['name'] === 'Aya Kouassi';
        });
    }

    public function test_les_listes_de_controle_ignorent_les_inscriptions_annulees_ou_sans_table(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $tenant->asCurrent(function () use ($event) {
            $table = SeatingTable::factory()->create(['event_id' => $event->id, 'number' => 1]);

            $seated = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'name' => 'Aya Kouassi']);
            RegistrationTableAssignment::factory()->create(['registration_id' => $seated->id, 'seating_table_id' => $table->id]);

            // Confirmee mais sans table : rien a cocher a l'entree tant qu'elle n'est pas placee.
            Registration::factory()->confirmed()->create(['event_id' => $event->id, 'name' => 'Sans Table']);
            Registration::factory()->cancelled()->create(['event_id' => $event->id, 'name' => 'Annule Un']);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.checklists', [$tenant, $event]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
            $names = collect($pdf->viewData['tables'])
                ->flatMap(fn (array $table) => collect($table['registrations'])->pluck('name'))
                ->all();

            return $names === ['Aya Kouassi'];
        });
    }

    public function test_les_listes_de_controle_ignorent_le_filtre_de_l_ecran(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $tenant->asCurrent(function () use ($event) {
            $table = SeatingTable::factory()->create(['event_id' => $event->id, 'number' => 1]);
            $seated = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'name' => 'Aya Kouassi']);
            RegistrationTableAssignment::factory()->create(['registration_id' => $seated->id, 'seating_table_id' => $table->id]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.checklists', [$tenant, $event]).'?filter[search]=introuvable')
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => count($pdf->viewData['tables']) === 1);
    }

    public function test_les_listes_de_controle_d_un_autre_evenement_ne_fuient_pas(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $otherEvent = $this->eventOf($tenant);

        $tenant->asCurrent(function () use ($otherEvent) {
            $table = SeatingTable::factory()->create(['event_id' => $otherEvent->id, 'number' => 1]);
            $other = Registration::factory()->confirmed()->create(['event_id' => $otherEvent->id, 'name' => 'Autre Evenement']);
            RegistrationTableAssignment::factory()->create(['registration_id' => $other->id, 'seating_table_id' => $table->id]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.checklists', [$tenant, $event]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => count($pdf->viewData['tables']) === 0);
    }

    public function test_les_listes_de_controle_exigent_la_permission_d_export(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.registrations.export.checklists', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_les_exports_pdf_partagent_le_limiteur_de_debit_des_exports(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($owner)
                ->get(route('tenants.events.registrations.export.pdf', [$tenant, $event]))
                ->assertOk();
        }

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.checklists', [$tenant, $event]))
            ->assertTooManyRequests();
    }
}
