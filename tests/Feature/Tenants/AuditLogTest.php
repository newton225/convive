<?php

namespace Tests\Feature\Tenants;

use App\Actions\Events\SaveEvent;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tenants\SaveTenantOrganisation;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * La journalisation (README ecran 23) : lecture des vraies entrees d'`activity_log`, recherche,
 * filtre de type et pagination cote serveur (`spatie/laravel-query-builder`), adresse IP et
 * agent utilisateur captures automatiquement (CLAUDE.md, « Securite »).
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    private function memberWith(TenantPermission ...$permissions): User
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, $permissions);

        return $member;
    }

    public function test_un_membre_avec_la_permission_voit_le_journal(): void
    {
        $this->tenant->asCurrent(function () {
            $event = Event::factory()->create(['name' => 'Diner de gala']);
            app(SaveEvent::class)->publish($event);
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.audit.index', $this->tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tenants/audit')
                ->has('entries')
                ->has('entries.0', fn ($entry) => $entry
                    ->hasAll(['id', 'type', 'actor', 'subject', 'ip', 'at']),
                )
                ->has('meta')
                ->has('types'),
            );
    }

    public function test_l_entree_porte_l_acteur_et_l_adresse_ip_de_la_requete(): void
    {
        $this->tenant->asCurrent(fn () => Event::factory()->create());

        $this->actingAs($this->owner)
            ->get(route('tenants.audit.index', $this->tenant))
            ->assertInertia(fn ($page) => $page
                ->where('entries.0.actor', $this->owner->name)
                ->where('entries.0.ip', '127.0.0.1'),
            );
    }

    public function test_la_recherche_filtre_par_description(): void
    {
        $this->tenant->asCurrent(function () {
            Event::factory()->create();
            app(SaveTenantOrganisation::class)->subdomain($this->tenant->fresh(), 'convive');
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.audit.index', $this->tenant).'?filter[search]=subdomain')
            ->assertInertia(fn ($page) => $page->has('entries', 1));
    }

    public function test_le_filtre_de_type_ne_montre_que_ce_type(): void
    {
        $this->tenant->asCurrent(function () {
            Event::factory()->create();
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.audit.index', $this->tenant).'?filter[type]=event.created')
            ->assertInertia(fn ($page) => $page
                ->has('entries', 1)
                ->where('entries.0.type', 'event.created'),
            );
    }

    public function test_un_membre_sans_la_permission_ne_voit_pas_le_journal(): void
    {
        $this->actingAs($this->memberWith(TenantPermission::EventsView))
            ->get(route('tenants.audit.index', $this->tenant))
            ->assertForbidden();
    }

    public function test_un_membre_avec_la_seule_permission_de_journal_le_voit(): void
    {
        $this->actingAs($this->memberWith(TenantPermission::AuditView))
            ->get(route('tenants.audit.index', $this->tenant))
            ->assertOk();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_journal(): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route('tenants.audit.index', $this->tenant))
            ->assertNotFound();
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion_depuis_le_journal(): void
    {
        $this->get(route('tenants.audit.index', $this->tenant))
            ->assertRedirect(route('login'));
    }

    public function test_le_journal_d_un_locataire_ne_montre_pas_celui_d_un_autre(): void
    {
        $otherOwner = User::factory()->withTwoFactor()->create();
        $other = app(CreateTenant::class)->handle($otherOwner, 'Autre Association');
        $other->asCurrent(fn () => Event::factory()->create(['name' => 'Evenement etranger']));

        $this->tenant->asCurrent(fn () => Event::factory()->create(['name' => 'Notre evenement']));

        $entries = $this->tenant->asCurrent(fn () => Activity::count());

        $this->assertSame(1, $entries);
    }
}
