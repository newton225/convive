<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tenants\SyncPermissionCatalogue;
use App\Enums\StarterProfile;
use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Les profils de base Tresorier, Hotesse et Lecture (decision du proprietaire du projet,
 * 2026-10-07) : ni modifiables ni supprimables, masquables par l'organisation, et un profil masque
 * n'est propose dans aucun formulaire. Ceux qui le portent deja le gardent.
 */
class StarterProfileTest extends TestCase
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

    private function profile(string $name): Profile
    {
        return $this->tenant->run(fn () => Profile::with('permissions')->where('name', $name)->firstOrFail());
    }

    private function hide(string $name, bool $hidden = true, ?User $actor = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->owner)
            ->patch(route('tenants.profiles.visibility', [$this->tenant, $this->profile($name)]), ['hidden' => $hidden]);
    }

    public function test_les_trois_profils_de_base_sont_reconnus_comme_tels(): void
    {
        foreach (StarterProfile::cases() as $starter) {
            $profile = $this->profile($starter->profileName());

            $this->assertTrue($profile->isStarter());
            $this->assertSame($starter, $profile->starter());
        }

        $this->assertFalse($this->profile(Profile::Owner)->isStarter());
    }

    public function test_un_profil_de_base_ne_peut_pas_etre_modifie(): void
    {
        $treasurer = $this->profile('Tresorier');
        $before = $treasurer->permissionValues();

        $this->actingAs($this->owner)
            ->patch(route('tenants.profiles.update', [$this->tenant, $treasurer]), [
                'name' => 'Comptable',
                'permissions' => [TenantPermission::EventsView->value],
            ])
            ->assertForbidden();

        $after = $this->profile('Tresorier');
        $this->assertSame('Tresorier', $after->name);
        $this->assertEqualsCanonicalizing($before, $after->permissionValues());
    }

    public function test_un_profil_de_base_ne_peut_pas_etre_supprime(): void
    {
        foreach (['Tresorier', 'Hotesse', 'Lecture'] as $name) {
            $profile = $this->profile($name);

            $this->actingAs($this->owner)
                ->delete(route('tenants.profiles.destroy', [$this->tenant, $profile]))
                ->assertForbidden();

            $this->tenant->run(fn () => $this->assertDatabaseHas('profiles', ['id' => $profile->id]));
        }
    }

    public function test_le_proprietaire_masque_puis_reaffiche_un_profil_de_base(): void
    {
        $this->hide('Hotesse')->assertRedirect(route('tenants.profiles.index', $this->tenant));
        $this->assertTrue($this->profile('Hotesse')->isHidden());

        $this->hide('Hotesse', false)->assertRedirect(route('tenants.profiles.index', $this->tenant));
        $this->assertFalse($this->profile('Hotesse')->isHidden());
    }

    public function test_le_masquage_est_journalise(): void
    {
        $this->hide('Lecture');

        $activity = $this->tenant->run(fn () => Activity::where('description', 'profile.hidden')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($this->owner->id, $activity->causer_id);
    }

    public function test_un_profil_cree_par_l_organisation_ne_se_masque_pas_il_se_supprime(): void
    {
        $this->tenant->run(fn () => Profile::create(['name' => 'Accueil', 'guard_name' => 'web']));

        $this->hide('Accueil')->assertForbidden();
    }

    public function test_le_profil_proprietaire_ne_se_masque_pas(): void
    {
        $this->hide(Profile::Owner)->assertForbidden();
    }

    public function test_sans_la_gestion_des_profils_le_masquage_est_refuse(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::TeamView]);

        $this->hide('Hotesse', actor: $member)->assertForbidden();
        $this->assertFalse($this->profile('Hotesse')->isHidden());
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($stranger, 'Autre organisation');

        $this->hide('Hotesse', actor: $stranger)->assertNotFound();
    }

    public function test_un_profil_masque_n_est_pas_propose_pour_les_membres_et_les_invitations(): void
    {
        $this->hide('Hotesse');

        $this->actingAs($this->owner)
            ->get(route('tenants.edit', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('availableProfiles', fn ($profiles) => ! collect($profiles)->pluck('name')->contains('Hotesse')
                    && collect($profiles)->pluck('name')->contains('Lecture')));
    }

    public function test_un_profil_masque_ne_peut_pas_etre_choisi_pour_une_invitation(): void
    {
        Notification::fake();
        $this->hide('Hotesse');

        $this->actingAs($this->owner)
            ->post(route('tenants.invitations.store', $this->tenant), [
                'email' => 'accueil@example.com',
                'profile_id' => $this->profile('Hotesse')->id,
            ])
            ->assertSessionHasErrors('profile_id');

        $this->assertDatabaseMissing('tenant_invitations', ['email' => 'accueil@example.com']);
    }

    public function test_un_profil_masque_ne_peut_pas_etre_affecte_a_un_membre(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithProfile($this->tenant, $member, 'Lecture');
        $this->hide('Hotesse');

        $this->actingAs($this->owner)
            ->patch(route('tenants.members.update', [$this->tenant, $member]), [
                'profile_id' => $this->profile('Hotesse')->id,
            ])
            ->assertSessionHasErrors('profile_id');

        $this->assertSame('Lecture', $member->fresh()->tenantProfile($this->tenant)?->name);
    }

    public function test_le_membre_qui_porte_un_profil_masque_le_garde_avec_ses_droits(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithProfile($this->tenant, $member, 'Hotesse');

        $this->hide('Hotesse');

        $this->assertSame('Hotesse', $member->fresh()->tenantProfile($this->tenant)?->name);
        $this->assertTrue($member->fresh()->hasTenantPermission($this->tenant, TenantPermission::ScanPerform));
    }

    public function test_la_liste_des_profils_signale_les_profils_de_base_et_masques(): void
    {
        $this->hide('Lecture');

        $this->actingAs($this->owner)
            ->get(route('tenants.profiles.index', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('profiles', function ($profiles) {
                    $byName = collect($profiles)->keyBy('name');

                    return $byName['Lecture']['isStarter'] === true
                        && $byName['Lecture']['isHidden'] === true
                        && $byName['Tresorier']['isStarter'] === true
                        && $byName['Tresorier']['isHidden'] === false
                        && $byName[Profile::Owner]['isStarter'] === false;
                }));
    }

    public function test_dupliquer_un_profil_de_base_donne_un_profil_modifiable(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.profiles.duplicate', [$this->tenant, $this->profile('Tresorier')]))
            ->assertRedirect();

        $copy = $this->tenant->run(fn () => Profile::where('name', '!=', 'Tresorier')
            ->where('name', 'like', '%Tresorier%')
            ->firstOrFail());

        $this->assertFalse($copy->isStarter());

        $this->actingAs($this->owner)
            ->get(route('tenants.profiles.edit', [$this->tenant, $copy]))
            ->assertOk();
    }

    public function test_la_synchronisation_remet_les_reglages_d_origine_des_profils_de_base(): void
    {
        // Un profil de base remanie avant que la regle n'existe : la synchronisation de deploiement
        // (`tenants:sync-permissions`) lui rend ses permissions et son exigence de double
        // authentification d'origine.
        $this->tenant->run(function () {
            $treasurer = Profile::where('name', 'Tresorier')->firstOrFail();
            $treasurer->syncPermissions([TenantPermission::EventsView->value, TenantPermission::BillingManage->value]);
            $treasurer->requires_two_factor = false;
            $treasurer->save();

            app(SyncPermissionCatalogue::class)->handle();
        });

        $treasurer = $this->profile('Tresorier');

        $this->assertEqualsCanonicalizing(StarterProfile::Treasurer->permissionValues(), $treasurer->permissionValues());
        $this->assertTrue($treasurer->requires_two_factor);
    }

    public function test_la_synchronisation_recree_un_profil_de_base_supprime(): void
    {
        $this->tenant->run(function () {
            Profile::where('name', 'Lecture')->firstOrFail()->delete();

            app(SyncPermissionCatalogue::class)->handle();
        });

        $reader = $this->profile('Lecture');

        $this->assertSame(StarterProfile::Reader, $reader->starter());
        $this->assertEqualsCanonicalizing(StarterProfile::Reader->permissionValues(), $reader->permissionValues());
    }

    public function test_la_synchronisation_garde_le_masquage(): void
    {
        $this->hide('Hotesse');

        $this->tenant->run(fn () => app(SyncPermissionCatalogue::class)->handle());

        $this->assertTrue($this->profile('Hotesse')->isHidden());
    }
}
