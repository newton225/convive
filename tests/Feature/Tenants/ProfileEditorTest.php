<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * L'editeur de profils (CLAUDE.md, « Profils et permissions ») : un ecran complet, le nom du profil
 * puis un module par ecran du back-office, chacun avec ses actions a cocher.
 */
class ProfileEditorTest extends TestCase
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

    private function profileId(string $name): int
    {
        return $this->tenant->run(fn () => Profile::where('name', $name)->value('id'));
    }

    public function test_l_ecran_de_creation_presente_un_module_par_ecran_avec_ses_actions(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.profiles.create', $this->tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenants/profile-form')
                ->where('profile', null)
                ->where('catalogue.0.value', 'events')
                ->where('catalogue.0.permissions.1.value', TenantPermission::EventsCreate->value)
                // Libelles en clair, pas la cle brute : la valeur `events.create` contient un point
                // que le traducteur prenait pour une imbrication (bogue corrige le 2026-09-27).
                ->where('catalogue.0.permissions.1.action', 'Créer')
                ->where('catalogue.0.permissions.1.label', 'Créer un événement')
                ->where('catalogue.0.permissions.1.requires', TenantPermission::EventsView->value)
                ->where('catalogue', fn ($modules) => collect($modules)->pluck('value')->contains('brand')
                    && collect($modules)->pluck('value')->contains('payment_accounts')));
    }

    public function test_l_ecran_de_modification_charge_le_profil_et_ses_permissions(): void
    {
        $this->tenant->run(fn () => Profile::create(['name' => 'Accueil', 'guard_name' => 'web'])
            ->syncPermissions([TenantPermission::EventsView->value]));

        $this->actingAs($this->owner)
            ->get(route('tenants.profiles.edit', [$this->tenant, $this->profileId('Accueil')]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenants/profile-form')
                ->where('profile.name', 'Accueil')
                ->has('profile.permissions'));
    }

    public function test_un_profil_de_base_ne_s_ouvre_pas_en_modification(): void
    {
        foreach (['Tresorier', 'Hotesse', 'Lecture'] as $name) {
            $this->actingAs($this->owner)
                ->get(route('tenants.profiles.edit', [$this->tenant, $this->profileId($name)]))
                ->assertForbidden();
        }
    }

    public function test_le_profil_systeme_ne_s_ouvre_pas_en_modification(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.profiles.edit', [$this->tenant, $this->profileId(Profile::Owner)]))
            ->assertForbidden();
    }

    public function test_un_membre_sans_la_gestion_des_profils_est_refuse(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.profiles.create', $this->tenant))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($stranger, 'Autre organisation');

        $this->actingAs($stranger)
            ->get(route('tenants.profiles.create', $this->tenant))
            ->assertNotFound();
    }

    public function test_la_liste_des_profils_fournit_le_catalogue_pour_la_vue_comparative(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.profiles.index', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('catalogue.0.value', 'events')
                ->has('profiles', 4));
    }
}
