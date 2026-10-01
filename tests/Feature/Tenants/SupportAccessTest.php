<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * L'acces du support (README ecran 25 et section 3, « Console d'exploitation ») : seul un
 * Proprietaire ouvre a une personne nommee de l'equipe Convive un acces en lecture seule, de 24
 * heures au plus, revocable, dont chaque consultation est journalisee. Sans acces ouvert, un
 * compte de l'equipe Convive ne lit aucune donnee de l'organisation : 404.
 */
class SupportAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $operator;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        $this->operator = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test', 'support_available' => true]);
        config(['convive.console.operators' => ['support@convive.test']]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function open(User $actor, array $payload = []): TestResponse
    {
        return $this->actingAs($actor)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tenants.support-access.store', $this->tenant), [
                'operator_id' => $this->operator->id,
                'duration' => 4,
                ...$payload,
            ]);
    }

    private function grant(int $hours = 4): SupportAccessGrant
    {
        return SupportAccessGrant::create([
            'tenant_id' => $this->tenant->id,
            'operator_id' => $this->operator->id,
            'granted_by_id' => $this->owner->id,
            'expires_at' => now()->addHours($hours),
        ]);
    }

    public function test_un_proprietaire_ouvre_l_ecran_de_l_acces_du_support(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenants/support-access')
                ->where('activeAccess', null)
                ->where('pastAccesses', [])
                ->where('operators.0.id', $this->operator->id)
                ->where('durations', [1, 4, 12, 24]),
            );
    }

    public function test_un_membre_qui_n_est_pas_proprietaire_est_refuse(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, TenantPermission::cases());

        $this->actingAs($member)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertForbidden();

        $this->open($member)->assertForbidden();
        $this->assertSame(0, SupportAccessGrant::count());
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($stranger, 'Autre organisation');

        $this->actingAs($stranger)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertNotFound();

        $this->open($stranger)->assertNotFound();
    }

    public function test_le_proprietaire_ouvre_un_acces_nominatif_et_limite_dans_le_temps(): void
    {
        $this->freezeTime();

        $this->open($this->owner, ['duration' => 12])
            ->assertRedirect(route('tenants.support-access.show', $this->tenant));

        $grant = SupportAccessGrant::sole();

        $this->assertSame($this->tenant->id, $grant->tenant_id);
        $this->assertSame($this->operator->id, $grant->operator_id);
        $this->assertSame($this->owner->id, $grant->granted_by_id);
        $this->assertTrue($grant->expires_at->equalTo(now()->addHours(12)));
        $this->assertTrue($grant->isActive());

        $logged = $this->tenant->asCurrent(fn () => Activity::where('description', 'support_access.opened')->count());
        $this->assertSame(1, $logged);
    }

    public function test_une_duree_de_plus_de_24_heures_est_refusee(): void
    {
        $this->open($this->owner, ['duration' => 48])->assertSessionHasErrors('duration');

        $this->assertSame(0, SupportAccessGrant::count());
    }

    public function test_une_personne_hors_de_l_equipe_convive_ne_peut_pas_recevoir_d_acces(): void
    {
        $outsider = User::factory()->create();

        $this->open($this->owner, ['operator_id' => $outsider->id])->assertSessionHasErrors('operator_id');

        $this->assertSame(0, SupportAccessGrant::count());
    }

    public function test_une_personne_de_l_equipe_qui_ne_s_est_pas_rendue_visible_n_est_ni_listee_ni_choisissable(): void
    {
        $discreet = User::factory()->withTwoFactor()->create(['email' => 'discret@convive.test']);
        config(['convive.console.operators' => ['support@convive.test', 'discret@convive.test']]);

        $this->actingAs($this->owner)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->has('operators', 1)
                ->where('operators.0.id', $this->operator->id),
            );

        $this->open($this->owner, ['operator_id' => $discreet->id])->assertSessionHasErrors('operator_id');

        $this->assertSame(0, SupportAccessGrant::count());
    }

    public function test_chacun_choisit_d_apparaitre_ou_non_dans_la_liste(): void
    {
        $this->actingAs($this->operator)
            ->put(route('console.support-availability.update'), ['available' => false])
            ->assertRedirect(route('console.organisations.index'));

        $this->assertFalse($this->operator->fresh()->support_available);

        $this->actingAs($this->owner)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page->where('operators', []));

        $this->actingAs($this->operator)
            ->put(route('console.support-availability.update'), ['available' => true]);

        $this->assertTrue($this->operator->fresh()->support_available);
    }

    public function test_un_compte_hors_de_l_equipe_convive_ne_peut_pas_se_rendre_visible(): void
    {
        $this->actingAs($this->owner)
            ->put(route('console.support-availability.update'), ['available' => true])
            ->assertNotFound();

        $this->assertFalse($this->owner->fresh()->support_available);
    }

    public function test_se_masquer_ne_ferme_pas_un_acces_deja_ouvert(): void
    {
        $this->grant();
        $this->operator->forceFill(['support_available' => false])->save();

        $this->actingAs($this->operator)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertOk();
    }

    public function test_un_seul_acces_a_la_fois(): void
    {
        $this->grant();

        $this->open($this->owner)->assertSessionHasErrors('operator_id');

        $this->assertSame(1, SupportAccessGrant::count());
    }

    public function test_sans_acces_ouvert_un_compte_de_l_equipe_convive_recoit_404(): void
    {
        $this->actingAs($this->operator)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertNotFound();
    }

    public function test_un_acces_ouvert_donne_la_lecture_et_journalise_chaque_page(): void
    {
        $grant = $this->grant();

        $this->actingAs($this->operator)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('supportAccess.viewing', true)
                ->where('currentTenant.slug', $this->tenant->slug),
            );

        $this->assertSame(['events'], $grant->views()->pluck('page')->all());

        $logged = $this->tenant->asCurrent(fn () => Activity::where('description', 'support_access.page_viewed')->count());
        $this->assertSame(1, $logged);
    }

    public function test_un_acces_ouvert_reste_en_lecture_seule(): void
    {
        $this->grant();
        $event = $this->tenant->asCurrent(fn () => Event::factory()->create());

        $this->actingAs($this->operator)
            ->post(route('tenants.events.store', $this->tenant), ['name' => 'Intrusion'])
            ->assertForbidden();

        $this->actingAs($this->operator)
            ->delete(route('tenants.events.destroy', [$this->tenant, $event]))
            ->assertForbidden();

        // Lire n'est pas exporter : la base d'inscrits ne sort pas de l'organisation.
        $this->actingAs($this->operator)
            ->get(route('tenants.events.registrations.export.excel', [$this->tenant, $event]))
            ->assertForbidden();

        $this->assertSame(1, $this->tenant->asCurrent(fn () => Event::count()));
    }

    public function test_l_acces_du_support_ne_permet_pas_de_gerer_l_acces_du_support(): void
    {
        $this->grant();

        $this->actingAs($this->operator)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertForbidden();
    }

    public function test_un_acces_expire_ne_donne_plus_rien(): void
    {
        $this->grant(hours: 1);

        $this->travel(61)->minutes();

        $this->actingAs($this->operator)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertNotFound();
    }

    public function test_un_acces_revoque_ne_donne_plus_rien(): void
    {
        $grant = $this->grant();

        $this->actingAs($this->owner)
            ->delete(route('tenants.support-access.destroy', [$this->tenant, $grant]))
            ->assertRedirect(route('tenants.support-access.show', $this->tenant));

        $this->assertNotNull($grant->fresh()->revoked_at);

        $this->actingAs($this->operator)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertNotFound();
    }

    public function test_un_acces_ne_vaut_que_pour_la_personne_nommee(): void
    {
        $this->grant();

        $colleague = User::factory()->withTwoFactor()->create(['email' => 'autre@convive.test']);
        config(['convive.console.operators' => ['support@convive.test', 'autre@convive.test']]);

        $this->actingAs($colleague)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertNotFound();
    }

    public function test_un_acces_ne_vaut_plus_si_son_porteur_quitte_l_equipe_convive(): void
    {
        $this->grant();
        config(['convive.console.operators' => []]);

        $this->actingAs($this->operator)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertNotFound();
    }

    public function test_on_ne_revoque_pas_l_acces_d_une_autre_organisation(): void
    {
        $grant = $this->grant();

        $other = User::factory()->withTwoFactor()->create();
        $otherTenant = app(CreateTenant::class)->handle($other, 'Autre organisation');

        $this->actingAs($other)
            ->delete(route('tenants.support-access.destroy', [$otherTenant, $grant]))
            ->assertNotFound();

        $this->assertNull($grant->fresh()->revoked_at);
    }

    public function test_les_membres_voient_qu_un_acces_est_ouvert(): void
    {
        $this->grant();

        $this->actingAs($this->owner)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('supportAccess.viewing', false)
                ->where('supportAccess.operator', $this->operator->name),
            );
    }
}
