<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\RefundStatus;
use App\Enums\RegistrationStatus;
use App\Enums\TenantPermission;
use App\Exports\RegistrationsExport;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
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

    public function test_le_tri_par_montant_decroissant_ordonne_les_lignes_et_revient_dans_les_filtres(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $tenant->asCurrent(function () use ($event) {
            Registration::factory()->create(['event_id' => $event->id, 'name' => 'Petit', 'amount_due' => 10000]);
            Registration::factory()->create(['event_id' => $event->id, 'name' => 'Grand', 'amount_due' => 90000]);
            Registration::factory()->create(['event_id' => $event->id, 'name' => 'Moyen', 'amount_due' => 40000]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]).'?sort=-amount_due')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.name', 'Grand')
                ->where('rows.1.name', 'Moyen')
                ->where('rows.2.name', 'Petit')
                ->where('filters.sort', '-amount_due'),
            );
    }

    public function test_un_tri_non_autorise_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        // Seuls les champs de `allowedSorts()` : un tri arbitraire ne doit pas atteindre la requete.
        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]).'?sort=phone')
            ->assertStatus(400);
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
            'inscrits-'.Str::slug($event->name).'.xlsx',
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

        Excel::assertDownloaded('inscrits-'.Str::slug($event->name).'.csv');
    }

    public function test_un_export_est_journalise_avec_le_nombre_de_lignes_et_les_filtres(): void
    {
        Excel::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $tenant->asCurrent(fn () => Registration::factory()->count(2)->confirmed()->create(['event_id' => $event->id]));

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.export.csv', [$tenant, $event]).'?filter[status]=confirmed')
            ->assertOk();

        // SECURITY.md M3 : l'export est autorise, ce n'est pas une intrusion, mais c'est une
        // fuite possible : l'entree dit qui, combien de lignes, avec quels filtres.
        $entry = $tenant->asCurrent(fn () => Activity::where('description', 'registrations.exported')->latest('id')->first());

        $this->assertNotNull($entry);
        $this->assertSame($owner->id, $entry->causer_id);
        $this->assertSame('csv', $entry->properties['format']);
        $this->assertSame(2, $entry->properties['rows']);
        $this->assertSame(['status' => 'confirmed'], $entry->properties['filters']);
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

    public function test_sans_la_permission_de_remboursement_l_annulation_ne_fixe_que_a_rembourser(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'amount_due' => 60000]));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView, TenantPermission::RegistrationsCancel]);

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), [
                'reason' => 'Motif.',
                'refund' => ['status' => 'kept', 'kept_reason' => 'Don.'],
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), ['reason' => 'Motif.'])
            ->assertRedirect();

        $this->assertSame(RefundStatus::Due, $tenant->asCurrent(fn () => $registration->fresh())->refund_status);
    }

    public function test_avec_la_permission_de_remboursement_l_annulation_fixe_le_sort_du_paiement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'amount_due' => 60000]));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [
            TenantPermission::RegistrationsView,
            TenantPermission::RegistrationsCancel,
            TenantPermission::RegistrationsRefund,
        ]);

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), [
                'reason' => 'Motif.',
                'refund' => [
                    'status' => 'refunded',
                    'channel' => 'wave',
                    'refunded_on' => '2026-09-28',
                    'fee' => 600,
                    'reference' => 'WAVE-123',
                ],
            ])
            ->assertRedirect();

        $fresh = $tenant->asCurrent(fn () => $registration->fresh());
        $this->assertSame(RefundStatus::Refunded, $fresh->refund_status);
        $this->assertSame(600, $fresh->refund_fee);
    }

    public function test_un_paiement_conserve_exige_un_motif(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'amount_due' => 60000]));

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), [
                'reason' => 'Motif.',
                'refund' => ['status' => 'kept', 'kept_reason' => ''],
            ])
            ->assertSessionHasErrors('refund.kept_reason');
    }

    public function test_un_remboursement_exige_moyen_date_et_frais(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'amount_due' => 60000]));

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), [
                'reason' => 'Motif.',
                'refund' => ['status' => 'refunded'],
            ])
            ->assertSessionHasErrors(['refund.channel', 'refund.refunded_on', 'refund.fee']);
    }

    public function test_des_frais_atteignant_le_montant_paye_sont_refuses_a_la_validation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'amount_due' => 20000]));

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), [
                'reason' => 'Motif.',
                'refund' => ['status' => 'refunded', 'channel' => 'wave', 'refunded_on' => '2026-09-28', 'fee' => 20000],
            ])
            ->assertSessionHasErrors('refund.fee');
    }

    public function test_une_date_de_remboursement_future_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'amount_due' => 60000]));

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.cancel', [$tenant, $event, $registration]), [
                'reason' => 'Motif.',
                'refund' => ['status' => 'refunded', 'channel' => 'wave', 'refunded_on' => now()->addDay()->toDateString(), 'fee' => 0],
            ])
            ->assertSessionHasErrors('refund.refunded_on');
    }

    public function test_un_membre_avec_la_permission_marque_un_remboursement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->refundDue()->create(['event_id' => $event->id, 'amount_due' => 60000]));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView, TenantPermission::RegistrationsRefund]);

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.refund', [$tenant, $event, $registration]), [
                'channel' => 'orange_money',
                'refunded_on' => '2026-09-29',
                'fee' => 500,
            ])
            ->assertRedirect(route('tenants.events.registrations.index', [$tenant, $event]));

        $this->assertSame(RefundStatus::Refunded, $tenant->asCurrent(fn () => $registration->fresh())->refund_status);
    }

    public function test_un_membre_sans_la_permission_ne_marque_pas_de_remboursement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->refundDue()->create(['event_id' => $event->id, 'amount_due' => 60000]));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView, TenantPermission::RegistrationsCancel]);

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.refund', [$tenant, $event, $registration]), [
                'channel' => 'wave',
                'refunded_on' => '2026-09-29',
                'fee' => 0,
            ])
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_remboursement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->refundDue()->create(['event_id' => $event->id, 'amount_due' => 60000]));

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->post(route('tenants.events.registrations.refund', [$tenant, $event, $registration]), [
                'channel' => 'wave',
                'refunded_on' => '2026-09-29',
                'fee' => 0,
            ])
            ->assertNotFound();
    }

    public function test_une_annulation_qui_n_est_plus_a_rembourser_ne_se_marque_pas_remboursee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->cancelled()->create([
            'event_id' => $event->id,
            'refund_status' => RefundStatus::Kept,
            'refund_kept_reason' => 'Don.',
        ]));

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.refund', [$tenant, $event, $registration]), [
                'channel' => 'wave',
                'refunded_on' => '2026-09-29',
                'fee' => 0,
            ])
            ->assertSessionHasErrors('refund');
    }
}
