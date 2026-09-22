<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Enums\TenantPermission;
use App\Exports\RegistrationsExport;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RegistrationControllerTest extends TestCase
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

    public function test_un_membre_avec_la_permission_voit_la_base_d_inscrits(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/registrations')
                ->has('rows', 1),
            );
    }

    public function test_un_membre_sans_la_permission_ne_voit_pas_la_base_d_inscrits(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_la_base_d_inscrits(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_le_filtre_de_statut_ne_retourne_que_les_lignes_attendues(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $tenant->asCurrent(function () use ($event) {
            Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Draft]);
            Registration::factory()->cancelled()->create(['event_id' => $event->id]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]).'?filter[status]=confirmed')
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.status', 'confirmed'));

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]).'?filter[status]=without_proof')
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.status', 'draft'));

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]).'?filter[status]=cancelled')
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.status', 'cancelled'));
    }

    public function test_la_recherche_filtre_par_nom_telephone_ou_email(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $tenant->asCurrent(function () use ($event) {
            Registration::factory()->create(['event_id' => $event->id, 'name' => 'Aya Kouassi']);
            Registration::factory()->create(['event_id' => $event->id, 'name' => 'Kofi Diallo']);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]).'?filter[search]=Kouassi')
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.name', 'Aya Kouassi'));
    }

    public function test_la_pagination_limite_a_vingt_cinq_lignes_par_page(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $tenant->asCurrent(fn () => Registration::factory()->count(30)->create(['event_id' => $event->id]));

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]))
            ->assertInertia(fn ($page) => $page
                ->has('rows', 25)
                ->where('meta.total', 30)
                ->where('meta.lastPage', 2),
            );
    }

    public function test_un_membre_avec_la_permission_annule_une_inscription(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView, TenantPermission::RegistrationsCancel]);

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), ['reason' => 'Motif.'])
            ->assertRedirect(route('tenants.events.registrations.index', [$tenant, $event]));

        $this->assertSame(
            RegistrationStatus::Cancelled,
            $tenant->asCurrent(fn () => $registration->fresh())->status,
        );
    }

    public function test_un_membre_sans_la_permission_d_annulation_ne_peut_pas_annuler(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView]);

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), ['reason' => 'Motif.'])
            ->assertForbidden();
    }

    public function test_le_motif_d_annulation_est_obligatoire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_une_inscription_d_un_autre_evenement_recoit_404_a_l_annulation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));
        $otherEvent = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $otherEvent, $registration]), ['reason' => 'Motif.'])
            ->assertNotFound();
    }

    public function test_un_membre_avec_la_permission_purge_les_inscriptions_non_finalisees(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $tenant->asCurrent(fn () => Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Draft]));

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.purge', [$tenant, $event]))
            ->assertRedirect(route('tenants.events.registrations.index', [$tenant, $event]));

        $this->assertSame(0, $tenant->asCurrent(fn () => Registration::where('event_id', $event->id)->count()));
    }

    public function test_un_membre_sans_la_permission_de_purge_ne_peut_pas_purger(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $tenant->asCurrent(fn () => Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Draft]));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView]);

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.purge', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_l_export_excel_reflete_le_filtre_actif(): void
    {
        Excel::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $tenant->asCurrent(function () use ($event) {
            Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            Registration::factory()->cancelled()->create(['event_id' => $event->id]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.excel', [$tenant, $event]).'?filter[status]=confirmed')
            ->assertOk();

        Excel::assertDownloaded(
            "inscrits-{$event->id}.xlsx",
            fn (RegistrationsExport $export) => $tenant->asCurrent(fn () => $export->query()->count()) === 1,
        );
    }

    public function test_un_membre_sans_la_permission_d_export_ne_peut_pas_exporter(): void
    {
        Excel::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.registrations.export.excel', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_a_l_export(): void
    {
        Excel::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.registrations.export.excel', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_l_export_csv_utilise_le_bon_format(): void
    {
        Excel::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.csv', [$tenant, $event]))
            ->assertOk();

        Excel::assertDownloaded("inscrits-{$event->id}.csv");
    }

    public function test_les_exports_sont_limites_en_debit(): void
    {
        Excel::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($owner)
                ->get(route('tenants.events.registrations.export.excel', [$tenant, $event]))
                ->assertOk();
        }

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.excel', [$tenant, $event]))
            ->assertTooManyRequests();
    }
}
