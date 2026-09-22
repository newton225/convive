<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorRequirementTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    private function profileOf(Tenant $tenant, string $name): Profile
    {
        return $tenant->run(fn () => Profile::where('name', $name)->firstOrFail());
    }

    public function test_les_profils_proprietaire_et_tresorier_exigent_la_double_authentification(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->create());

        $this->assertTrue($this->profileOf($tenant, Profile::Owner)->requires_two_factor);
        $this->assertTrue($this->profileOf($tenant, 'Tresorier')->requires_two_factor);
    }

    public function test_les_profils_hotesse_et_lecture_n_exigent_pas_la_double_authentification(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->create());

        $this->assertFalse($this->profileOf($tenant, 'Hotesse')->requires_two_factor);
        $this->assertFalse($this->profileOf($tenant, 'Lecture')->requires_two_factor);
    }

    public function test_un_membre_sans_double_authentification_est_renvoye_vers_la_securite(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('tenants.edit', $tenant))
            ->assertRedirect(route('security.edit'));
    }

    public function test_le_tableau_de_bord_est_ferme_tant_que_la_double_authentification_manque(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('dashboard', ['current_tenant' => $tenant->slug]))
            ->assertRedirect(route('security.edit'));
    }

    public function test_un_membre_avec_double_authentification_accede_a_l_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('tenants.edit', $tenant))
            ->assertOk();
    }

    public function test_un_profil_qui_n_exige_rien_laisse_passer_sans_double_authentification(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $reader = User::factory()->create();
        $tenant->addMember($reader, $this->profileOf($tenant, 'Lecture'));

        $this->actingAs($reader)
            ->get(route('tenants.edit', $tenant))
            ->assertOk();
    }

    public function test_un_membre_bloque_peut_toujours_quitter_l_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $treasurer = User::factory()->create();
        $tenant->addMember($treasurer, $this->profileOf($tenant, 'Tresorier'));

        $this->actingAs($treasurer)
            ->delete(route('tenants.leave', $tenant))
            ->assertRedirect();

        $this->assertFalse($treasurer->fresh()->belongsToTenant($tenant));
    }

    public function test_un_membre_bloque_peut_toujours_changer_d_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $treasurer = User::factory()->create();
        $tenant->addMember($treasurer, $this->profileOf($tenant, 'Tresorier'));

        $this->actingAs($treasurer)
            ->post(route('tenants.switch', $tenant))
            ->assertRedirect();

        $this->assertTrue($treasurer->fresh()->isCurrentTenant($tenant));
    }

    public function test_l_exigence_peut_etre_desactivee_par_configuration(): void
    {
        config(['convive.two_factor.enforced' => false]);

        $owner = User::factory()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('tenants.edit', $tenant))
            ->assertOk();
    }

    public function test_l_exigence_est_enregistree_avec_le_profil(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.profiles.store', $tenant), [
                'name' => 'Comptable',
                'permissions' => [TenantPermission::ProofsApprove->value],
                'requires_two_factor' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($this->profileOf($tenant, 'Comptable')->requires_two_factor);
    }

    public function test_le_profil_systeme_exige_toujours_la_double_authentification(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->create());

        $demandsTwoFactor = $tenant->run(function () {
            $ownerProfile = Profile::where('name', Profile::Owner)->firstOrFail();
            $ownerProfile->requires_two_factor = false;
            $ownerProfile->save();

            return $ownerProfile->fresh()->demandsTwoFactor();
        });

        $this->assertTrue($demandsTwoFactor);
    }
}
