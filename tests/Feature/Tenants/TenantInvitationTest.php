<?php

namespace Tests\Feature\Tenants;

use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\Tenants\TenantInvitation as TenantInvitationNotification;
use App\Support\GettingStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TenantInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function profileOf(Tenant $tenant, string $name): Profile
    {
        return $tenant->run(fn () => Profile::where('name', $name)->firstOrFail());
    }

    public function test_tenant_invitations_can_be_created()
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $response = $this
            ->actingAs($owner)
            ->post(route('tenants.invitations.store', $tenant), [
                'email' => 'invited@example.com',
                'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            ]);

        $response->assertRedirect(route('tenants.edit', $tenant));

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
            'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
        ]);
    }

    public function test_invitation_email_for_existing_users_uses_login_route()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $invitedUser = User::factory()->withTwoFactor()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            'email' => $invitedUser->email,
            'invited_by' => $owner->id,
        ]);

        $mail = (new TenantInvitationNotification($invitation))->toMail($invitedUser);

        $this->assertSame(route('login', ['invitation' => $invitation->code]), $mail->actionUrl);
        $this->assertStringContainsString('connectez-vous', strtolower(implode(' ', $mail->introLines)));
    }

    public function test_invitation_email_for_unknown_users_uses_register_route()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            'email' => 'unknown@example.com',
            'invited_by' => $owner->id,
        ]);

        $mail = (new TenantInvitationNotification($invitation))->toMail((object) []);

        // Aucun compte a cette adresse : le lien mene droit a l'inscription (TODO du 2026-10-07).
        $this->assertSame(route('register', ['invitation' => $invitation->code]), $mail->actionUrl);
    }

    public function test_tenant_invitations_can_be_created_by_admins()
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $admin = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithPermissions($tenant, $admin, [
            TenantPermission::TenantBranding,
            TenantPermission::TeamView,
            TenantPermission::TeamInvite,
            TenantPermission::TeamRemove,
            TenantPermission::ProfilesManage,
        ], 'Administrateur');

        $response = $this
            ->actingAs($admin)
            ->post(route('tenants.invitations.store', $tenant), [
                'email' => 'invited@example.com',
                'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            ]);

        $response->assertRedirect(route('tenants.edit', $tenant));
    }

    public function test_existing_tenant_members_cannot_be_invited()
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create(['email' => 'member@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $response = $this
            ->actingAs($owner)
            ->post(route('tenants.invitations.store', $tenant), [
                'email' => 'member@example.com',
                'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_duplicate_invitations_cannot_be_created()
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();
        $this->joinAsOwner($tenant, $owner);

        TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->post(route('tenants.invitations.store', $tenant), [
                'email' => 'invited@example.com',
                'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_tenant_invitations_cannot_be_created_by_members()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $member = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);
        $this->joinWithProfile($tenant, $member, 'Lecture');

        $response = $this
            ->actingAs($member)
            ->post(route('tenants.invitations.store', $tenant), [
                'email' => 'invited@example.com',
                'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            ]);

        $response->assertForbidden();
    }

    public function test_tenant_invitations_can_be_cancelled_by_owners()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('tenants.invitations.destroy', [$tenant, $invitation]));

        $response->assertRedirect(route('tenants.edit', $tenant));

        $this->assertDatabaseMissing('tenant_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_tenant_invitations_can_be_accepted()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $invitedUser = User::factory()->withTwoFactor()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertRedirect(route('dashboard', $tenant));
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Invitation acceptée.']);

        $this->assertTrue($invitedUser->fresh()->belongsToTenant($tenant));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_tenant_invitations_can_be_declined_by_the_invited_user()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $invitedUser = User::factory()->withTwoFactor()->withoutOrganisation()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        // Sans organisation, la personne revient a l'accueil des invitations, qui propose d'en creer une.
        $response->assertRedirect(route('invitations.index'));

        $this->assertDatabaseMissing('tenant_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_tenant_invitations_cannot_be_declined_by_uninvited_user()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $uninvitedUser = User::factory()->withTwoFactor()->create(['email' => 'uninvited@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($uninvitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_accepted_tenant_invitations_cannot_be_declined()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $invitedUser = User::factory()->withTwoFactor()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->accepted()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_tenant_invitations_cannot_be_accepted_by_uninvited_user()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $uninvitedUser = User::factory()->withTwoFactor()->create(['email' => 'uninvited@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($uninvitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertFalse($uninvitedUser->fresh()->belongsToTenant($tenant));
    }

    public function test_expired_invitations_cannot_be_accepted()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $invitedUser = User::factory()->withTwoFactor()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->expired()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertFalse($invitedUser->fresh()->belongsToTenant($tenant));
    }

    public function test_une_invitation_envoyee_depuis_les_premiers_pas_ramene_au_tableau_de_bord(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = Tenant::factory()->create();
        $this->joinAsOwner($tenant, $owner);

        $this->actingAs($owner)
            ->post(route('tenants.invitations.store', [$tenant, ...GettingStarted::ReturnQuery]), [
                'email' => 'invited@example.com',
                'profile_id' => $this->profileOf($tenant, 'Lecture')->id,
            ])
            ->assertRedirect(route('dashboard', $tenant));
    }
}
