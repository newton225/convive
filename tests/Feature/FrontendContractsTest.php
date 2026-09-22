<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les contrats serveur vers client que l'interface complete a ajoutes : le tableau de bord
 * (README ecran 17), la cle publique de verification du scan hors ligne (README 2.8) et les
 * permissions partagees qui masquent les liens interdits du menu.
 */
class FrontendContractsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->owner->switchTenant($this->tenant);
    }

    public function test_le_tableau_de_bord_porte_un_apercu_d_exemple_annonce_comme_tel(): void
    {
        $this->actingAs($this->owner->fresh())
            ->get(route('dashboard', $this->tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('overview.isSample', true)
                ->has('overview.kpis', fn ($kpis) => $kpis->hasAll(['registrations', 'validated', 'toCheck', 'withoutProof', 'seatsLeft']))
                ->has('overview.registrationsPerDay', 14)
                ->has('overview.proofsByChannel')
                ->has('overview.tableOccupancy')
                ->has('overview.recentActivity'),
            );
    }

    public function test_les_chiffres_d_exemple_du_tableau_de_bord_sont_coherents_entre_eux(): void
    {
        $this->actingAs($this->owner->fresh())
            ->get(route('dashboard', $this->tenant))
            ->assertInertia(fn ($page) => $page
                ->where('overview.kpis', function ($kpis) {
                    $kpis = collect($kpis);

                    return $kpis['registrations'] === $kpis['validated'] + $kpis['toCheck'] + $kpis['withoutProof'];
                })
                ->where('overview.registrationsPerDay', fn ($days) => collect($days)->sum('count') === 142),
            );
    }

    public function test_l_ecran_de_scan_donne_la_cle_publique_jamais_la_cle_privee(): void
    {
        $event = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $event->ensureSigningKeyPair();

            return $event->fresh();
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenantId', $this->tenant->id)
                ->where('event.qrPublicKey', $event->qr_public_key)
                ->missing('event.qrSecretKey')
                ->missing('event.qr_secret_key'),
            );
    }

    public function test_sans_billet_emis_l_ecran_de_scan_n_a_pas_encore_de_cle(): void
    {
        $event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());

        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $event]))
            ->assertInertia(fn ($page) => $page->where('event.qrPublicKey', null));
    }

    public function test_les_permissions_de_l_organisation_courante_sont_partagees_avec_toutes_les_pages(): void
    {
        $this->actingAs($this->owner->fresh())
            ->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('tenantPermissions.values', fn ($values) => collect($values)->contains(TenantPermission::AuditView->value)),
            );
    }

    public function test_un_membre_limite_ne_recoit_que_ses_permissions(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::EventsView]);
        $member->switchTenant($this->tenant);

        $this->actingAs($member->fresh())
            ->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('tenantPermissions.values', fn ($values) => collect($values)->contains('events.view')
                    && ! collect($values)->contains('audit.view')),
            );
    }
}
