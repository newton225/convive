<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\NotificationType;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

/**
 * Surveillance des exports legitimes (SECURITY.md M3) : alerte a ceux qui surveillent le journal
 * au dela d'un seuil de lignes, et filigrane portant l'identite du demandeur sur les PDF.
 */
class ExportOversightTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.exports.alert_rows' => 3]);

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create(['name' => 'Diner de gala']));
    }

    private function registrations(int $count): void
    {
        $this->tenant->asCurrent(fn () => Registration::factory()->count($count)->confirmed()->create(['event_id' => $this->event->id]));
    }

    private function exporter(): User
    {
        $member = User::factory()->withTwoFactor()->create(['name' => 'Kofi Diallo']);
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::RegistrationsView, TenantPermission::RegistrationsExport]);

        return $member;
    }

    public function test_un_export_au_dela_du_seuil_previent_le_proprietaire(): void
    {
        Notification::fake();
        $this->registrations(3);

        $this->actingAs($this->exporter())
            ->get(route('tenants.events.registrations.export.csv', [$this->tenant, $this->event]))
            ->assertOk();

        Notification::assertSentTo($this->owner, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::LargeExport);
    }

    public function test_un_export_sous_le_seuil_ne_previent_personne(): void
    {
        Notification::fake();
        $this->registrations(2);

        $this->actingAs($this->exporter())
            ->get(route('tenants.events.registrations.export.csv', [$this->tenant, $this->event]))
            ->assertOk();

        Notification::assertNotSentTo($this->owner, TenantAlert::class);
    }

    public function test_l_auteur_de_l_export_n_est_pas_prevenu_de_son_propre_geste(): void
    {
        Notification::fake();
        $this->registrations(3);

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.export.csv', [$this->tenant, $this->event]))
            ->assertOk();

        Notification::assertNotSentTo($this->owner, TenantAlert::class);
    }

    public function test_l_export_pdf_porte_un_filigrane_au_nom_du_demandeur(): void
    {
        Pdf::fake();
        $this->registrations(1);

        $this->actingAs($this->exporter())
            ->get(route('tenants.events.registrations.export.pdf', [$this->tenant, $this->event]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewData['watermark']['name'] === 'Kofi Diallo'
            && $pdf->viewData['watermark']['at'] !== null);
    }

    public function test_les_listes_de_controle_portent_aussi_le_filigrane(): void
    {
        Pdf::fake();

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.export.checklists', [$this->tenant, $this->event]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewData['watermark']['name'] === $this->owner->name);
    }

    public function test_le_rapport_pdf_porte_le_filigrane_et_son_export_est_journalise(): void
    {
        Pdf::fake();

        $this->actingAs($this->owner)
            ->get(route('tenants.events.report.export.pdf', [$this->tenant, $this->event]))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewData['watermark']['name'] === $this->owner->name);

        $this->tenant->asCurrent(function () {
            $this->assertDatabaseHas('activity_log', ['description' => 'report.exported']);
        });
    }
}
