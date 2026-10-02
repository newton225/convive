<?php

namespace Tests\Feature\Tenants;

use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tenants_index_page_can_be_rendered()
    {
        $user = User::factory()->withTwoFactor()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('tenants.index'));

        $response->assertOk();
    }

    public function test_tenants_can_be_created()
    {
        $user = User::factory()->withTwoFactor()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('tenants.store'), [
                'name' => 'Test Tenant',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Tenant',
            'is_personal' => false,
        ]);
    }

    public function test_personal_tenant_returns_the_tenant_owned_by_the_user()
    {
        $otherUser = User::factory()->withTwoFactor()->create();
        $user = User::factory()->make();
        $user->save();

        $this->joinWithProfile($otherUser->personalTenant(), $user, 'Lecture');

        $personalTenant = Tenant::factory()->personal()->create();
        $this->joinAsOwner($personalTenant, $user);

        $this->assertTrue($personalTenant->is($user->personalTenant()));
    }

    public function test_tenant_slug_uses_next_available_suffix()
    {
        $user = User::factory()->withTwoFactor()->create();

        Tenant::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        Tenant::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
        Tenant::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

        $this
            ->actingAs($user)
            ->post(route('tenants.store'), [
                'name' => 'Acme',
            ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme',
            'slug' => 'acme-11',
        ]);
    }

    public function test_the_tenant_edit_page_can_be_rendered()
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $user);

        $response = $this
            ->actingAs($user)
            ->get(route('tenants.edit', $tenant));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenants/edit')
                ->where('members.0.profileName', Profile::Owner)
                ->has('availableProfiles', 4),
            );
    }

    public function test_tenants_can_be_updated_by_owners()
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create(['name' => 'Original Name']);

        $this->joinAsOwner($tenant, $user);

        $response = $this
            ->actingAs($user)
            ->patch(route('tenants.update', $tenant), [
                'name' => 'Updated Name',
            ]);

        $response->assertRedirect(route('tenants.edit', $tenant->fresh()));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_tenants_cannot_be_updated_by_members()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $response = $this
            ->actingAs($member)
            ->patch(route('tenants.update', $tenant), [
                'name' => 'Updated Name',
            ]);

        $response->assertForbidden();
    }

    public function test_tenants_can_be_deleted_by_owners()
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $user);

        $response = $this
            ->actingAs($user)
            ->delete(route('tenants.destroy', $tenant), [
                'name' => $tenant->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('tenants', [
            'id' => $tenant->id,
        ]);
    }

    public function test_tenant_deletion_requires_name_confirmation()
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $user);

        $response = $this
            ->actingAs($user)
            ->delete(route('tenants.destroy', $tenant), [
                'name' => 'Wrong Name',
            ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleting_current_tenant_switches_to_alphabetically_first_remaining_tenant()
    {
        $user = User::factory()->withTwoFactor()->create(['name' => 'Mike']);

        $zuluTenant = Tenant::factory()->create(['name' => 'Zulu Tenant']);
        $this->joinAsOwner($zuluTenant, $user);

        $alphaTenant = Tenant::factory()->create(['name' => 'Alpha Tenant']);
        $this->joinAsOwner($alphaTenant, $user);

        $betaTenant = Tenant::factory()->create(['name' => 'Beta Tenant']);
        $this->joinAsOwner($betaTenant, $user);

        $user->update(['current_tenant_id' => $zuluTenant->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('tenants.destroy', $zuluTenant), [
                'name' => $zuluTenant->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('tenants', [
            'id' => $zuluTenant->id,
        ]);

        $this->assertEquals($alphaTenant->id, $user->fresh()->current_tenant_id);
    }

    public function test_deleting_current_tenant_falls_back_to_personal_tenant_when_alphabetically_first()
    {
        $user = User::factory()->withTwoFactor()->create();
        $personalTenant = $user->personalTenant();
        $tenant = Tenant::factory()->create(['name' => 'Zulu Tenant']);
        $this->joinAsOwner($tenant, $user);

        $user->update(['current_tenant_id' => $tenant->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('tenants.destroy', $tenant), [
                'name' => $tenant->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('tenants', [
            'id' => $tenant->id,
        ]);

        $this->assertEquals($personalTenant->id, $user->fresh()->current_tenant_id);
    }

    public function test_deleting_non_current_tenant_leaves_current_tenant_unchanged()
    {
        $user = User::factory()->withTwoFactor()->create();
        $personalTenant = $user->personalTenant();
        $tenant = Tenant::factory()->create();
        $this->joinAsOwner($tenant, $user);

        $user->update(['current_tenant_id' => $personalTenant->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('tenants.destroy', $tenant), [
                'name' => $tenant->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('tenants', [
            'id' => $tenant->id,
        ]);

        $this->assertEquals($personalTenant->id, $user->fresh()->current_tenant_id);
    }

    public function test_members_can_leave_non_personal_tenants()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $response = $this
            ->actingAs($member)
            ->delete(route('tenants.leave', $tenant));

        $response->assertRedirect(route('tenants.index'));
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => "Vous avez quitté l'organisation \"{$tenant->name}\"."]);

        $this->assertFalse($member->fresh()->belongsToTenant($tenant));
    }

    public function test_leaving_current_tenant_switches_to_alphabetically_first_remaining_tenant()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create(['name' => 'Mike']);

        $zuluTenant = Tenant::factory()->create(['name' => 'Zulu Tenant']);
        $this->joinAsOwner($zuluTenant, $owner);
        $this->joinWithProfile($zuluTenant, $member, 'Lecture');

        $alphaTenant = Tenant::factory()->create(['name' => 'Alpha Tenant']);
        $this->joinWithProfile($alphaTenant, $member, 'Lecture');

        $betaTenant = Tenant::factory()->create(['name' => 'Beta Tenant']);
        $this->joinWithProfile($betaTenant, $member, 'Lecture');

        $member->update(['current_tenant_id' => $zuluTenant->id]);

        $response = $this
            ->actingAs($member)
            ->delete(route('tenants.leave', $zuluTenant));

        $response->assertRedirect(route('tenants.index'));

        $this->assertFalse($member->fresh()->belongsToTenant($zuluTenant));
        $this->assertEquals($alphaTenant->id, $member->fresh()->current_tenant_id);
    }

    public function test_personal_tenants_cannot_be_left()
    {
        $user = User::factory()->withTwoFactor()->create();
        $personalTenant = $user->personalTenant();

        $response = $this
            ->actingAs($user)
            ->delete(route('tenants.leave', $personalTenant));

        $response->assertForbidden();

        $this->assertTrue($user->fresh()->belongsToTenant($personalTenant));
    }

    public function test_tenant_owners_cannot_leave_their_tenant()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $response = $this
            ->actingAs($owner)
            ->delete(route('tenants.leave', $tenant));

        $response->assertForbidden();

        $this->assertTrue($owner->fresh()->belongsToTenant($tenant));
    }

    public function test_users_cannot_leave_tenants_they_dont_belong_to()
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('tenants.leave', $tenant));

        $response->assertNotFound();
    }

    public function test_deleting_tenant_switches_other_affected_users_to_their_personal_tenant()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();

        $tenant = Tenant::factory()->create();
        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $owner->update(['current_tenant_id' => $tenant->id]);
        $member->update(['current_tenant_id' => $tenant->id]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('tenants.destroy', $tenant), [
                'name' => $tenant->name,
            ]);

        $response->assertRedirect();

        $this->assertEquals($member->personalTenant()->id, $member->fresh()->current_tenant_id);
    }

    public function test_personal_tenants_cannot_be_deleted()
    {
        $user = User::factory()->withTwoFactor()->create();

        $personalTenant = $user->personalTenant();

        $response = $this
            ->actingAs($user)
            ->delete(route('tenants.destroy', $personalTenant), [
                'name' => $personalTenant->name,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('tenants', [
            'id' => $personalTenant->id,
            'deleted_at' => null,
        ]);
    }

    public function test_tenants_cannot_be_deleted_by_non_owners()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $response = $this
            ->actingAs($member)
            ->delete(route('tenants.destroy', $tenant), [
                'name' => $tenant->name,
            ]);

        $response->assertForbidden();
    }

    public function test_un_membre_qui_gere_l_identite_legale_sans_etre_proprietaire_ne_supprime_pas_l_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $treasurer = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        // Corriger un numero de contribuable n'est pas decider de la fin de l'organisation.
        $this->joinWithPermissions($tenant, $treasurer, [TenantPermission::TenantLegal]);

        $this->actingAs($treasurer)
            ->delete(route('tenants.destroy', $tenant), ['name' => $tenant->name])
            ->assertForbidden();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'deleted_at' => null]);

        $this->actingAs($treasurer)
            ->get(route('tenants.edit', $tenant))
            ->assertInertia(fn ($page) => $page->where('canDelete', false));

        $this->actingAs($owner)
            ->get(route('tenants.edit', $tenant))
            ->assertInertia(fn ($page) => $page->where('canDelete', true));
    }

    public function test_users_can_switch_tenants()
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinWithProfile($tenant, $user, 'Lecture');

        $response = $this
            ->actingAs($user)
            ->post(route('tenants.switch', $tenant));

        $response->assertRedirect();
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('tenants.flash.switched', ['name' => $tenant->name])]);

        $this->assertEquals($tenant->id, $user->fresh()->current_tenant_id);
    }

    public function test_users_cannot_switch_to_tenant_they_dont_belong_to()
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('tenants.switch', $tenant));

        $response->assertNotFound();
    }

    public function test_guests_cannot_access_tenants()
    {
        $response = $this->get(route('tenants.index'));

        $response->assertRedirect(route('login'));
    }
}
