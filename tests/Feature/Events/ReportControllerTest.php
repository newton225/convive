<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

/**
 * Ecran 22, rapports post-evenement, etape 9 de « Ordre de construction ».
 */
class ReportControllerTest extends TestCase
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

    public function test_un_membre_avec_la_permission_voit_le_rapport(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'amount_due' => 15000]));

        $this->actingAs($owner)
            ->get(route('tenants.events.report.show', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/report')
                ->where('report.confirmedRegistrations', 1)
                ->where('report.collectedAmount', 15000),
            );
    }

    public function test_un_membre_sans_la_permission_ne_voit_pas_le_rapport(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.report.show', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_rapport(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.report.show', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_l_export_pdf_rend_la_vue_du_rapport(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->get(route('tenants.events.report.export.pdf', [$tenant, $event]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'pdf.report'
            && isset($pdf->viewData['report']['collectedAmount']));
    }

    public function test_l_export_pdf_exige_la_permission_d_export_des_rapports(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ReportsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.report.show', [$tenant, $event]))
            ->assertOk();

        $this->actingAs($member)
            ->get(route('tenants.events.report.export.pdf', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_l_export_pdf_du_rapport(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.report.export.pdf', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_l_export_pdf_du_rapport_est_limite_en_debit(): void
    {
        Pdf::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($owner)
                ->get(route('tenants.events.report.export.pdf', [$tenant, $event]))
                ->assertOk();
        }

        $this->actingAs($owner)
            ->get(route('tenants.events.report.export.pdf', [$tenant, $event]))
            ->assertTooManyRequests();
    }
}
