<?php

namespace Tests\Feature\Tenants;

use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMemberTest extends TestCase
{
    use RefreshDatabase;

    private function profileOf(Tenant $tenant, string $name): Profile
    {
        return $tenant->run(fn () => Profile::where('name', $name)->firstOrFail());
    }

    public function test_le_proprietaire_change_le_profil_d_un_membre(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $treasurer = $this->profileOf($tenant, 'Tresorier');

        $this->actingAs($owner)
            ->patch(route('tenants.members.update', [$tenant, $member]), [
                'profile_id' => $treasurer->id,
            ])
            ->assertRedirect(route('tenants.edit', $tenant));

        $this->assertSame('Tresorier', $member->fresh()->tenantProfile($tenant)?->name);
    }

    public function test_un_membre_sans_la_gestion_des_profils_ne_change_pas_les_affectations(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $target = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');
        $this->joinWithProfile($tenant, $target, 'Hotesse');

        $this->actingAs($member)
            ->patch(route('tenants.members.update', [$tenant, $target]), [
                'profile_id' => $this->profileOf($tenant, 'Tresorier')->id,
            ])
            ->assertForbidden();
    }

    public function test_on_ne_peut_pas_affecter_un_profil_plus_puissant_que_le_sien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $manager = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithPermissions($tenant, $manager, [
            TenantPermission::TeamView,
            TenantPermission::ProfilesManage,
        ], 'Gestionnaire');
        $this->joinWithProfile($tenant, $member, 'Lecture');

        // Affecter le profil Proprietaire reviendrait a accorder tout le catalogue.
        $this->actingAs($manager)
            ->patch(route('tenants.members.update', [$tenant, $member]), [
                'profile_id' => $this->profileOf($tenant, 'Proprietaire')->id,
            ])
            ->assertSessionHasErrors('profile_id');

        $this->assertSame('Lecture', $member->fresh()->tenantProfile($tenant)?->name);
    }

    public function test_un_proprietaire_peut_promouvoir_un_membre_proprietaire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $this->actingAs($owner)
            ->patch(route('tenants.members.update', [$tenant, $member]), [
                'profile_id' => $this->profileOf($tenant, 'Proprietaire')->id,
            ])
            ->assertRedirect();

        $this->assertTrue($member->fresh()->ownsTenant($tenant));
    }

    public function test_les_membres_sont_retires_par_le_proprietaire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $this->actingAs($owner)
            ->delete(route('tenants.members.destroy', [$tenant, $member]))
            ->assertRedirect(route('tenants.edit', $tenant));

        $this->assertFalse($member->fresh()->belongsToTenant($tenant));
    }

    public function test_un_membre_sans_la_permission_ne_retire_personne(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $target = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');
        $this->joinWithProfile($tenant, $target, 'Hotesse');

        $this->actingAs($member)
            ->delete(route('tenants.members.destroy', [$tenant, $target]))
            ->assertForbidden();
    }

    public function test_le_proprietaire_ne_peut_pas_etre_retire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $this->actingAs($owner)
            ->delete(route('tenants.members.destroy', [$tenant, $owner]))
            ->assertForbidden();

        $this->assertTrue($owner->fresh()->belongsToTenant($tenant));
    }

    public function test_un_membre_retire_revient_sur_son_organisation_personnelle(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $personalTenant = $member->personalTenant();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $member->update(['current_tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->delete(route('tenants.members.destroy', [$tenant, $member]));

        $this->assertEquals($personalTenant->id, $member->fresh()->current_tenant_id);
    }

    public function test_un_membre_retire_perd_son_profil(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Tresorier');

        $this->actingAs($owner)
            ->delete(route('tenants.members.destroy', [$tenant, $member]));

        $this->assertNull($member->fresh()->tenantProfile($tenant));
        $this->assertFalse($member->fresh()->hasTenantPermission($tenant, TenantPermission::ProofsApprove));
    }
}
