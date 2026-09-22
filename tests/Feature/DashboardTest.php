<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = $user->currentTenant;

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = $user->currentTenant;

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_dashboard_includes_pending_invitations_for_the_authenticated_user()
    {
        $owner = User::factory()->withTwoFactor()->create(['name' => 'Taylor Otwell']);
        $invitedUser = User::factory()->withTwoFactor()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create(['name' => 'Laravel Tenant']);

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.code', $invitation->code)
            ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
            ->where('pendingInvitations.0.tenant.name', 'Laravel Tenant')
            ->where('pendingInvitations.0.tenant.slug', $tenant->slug)
            ->missing('pendingInvitations.0.tenantName'),
        );
    }

    public function test_dashboard_does_not_include_accepted_invitations()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $invitedUser = User::factory()->withTwoFactor()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        TenantInvitation::factory()->accepted()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0),
        );
    }

    public function test_dashboard_excludes_expired_invitations_without_deleting_them()
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
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_dashboard_does_not_include_or_delete_other_users_invitations()
    {
        $owner = User::factory()->withTwoFactor()->create();
        $invitedUser = User::factory()->withTwoFactor()->create(['email' => 'invited@example.com']);
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->expired()->create([
            'tenant_id' => $tenant->id,
            'email' => 'someone@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
        ]);
    }

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    public function test_sans_evenement_l_apercu_est_absent(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('overview', null));
    }

    public function test_l_apercu_porte_les_indicateurs_du_seul_evenement_retenu(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create(['table_count' => 5, 'seats_per_table' => 10]);
            Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 2]);
            Registration::factory()->proofSubmitted()->create(['event_id' => $event->id, 'party_size' => 1]);
            Registration::factory()->held()->create(['event_id' => $event->id, 'party_size' => 1]);
        });

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.kpis.registrations', 3)
                ->where('overview.kpis.validated', 1)
                ->where('overview.kpis.toCheck', 1)
                ->where('overview.kpis.withoutProof', 1),
            );
    }

    public function test_l_evenement_ouvert_prime_sur_un_brouillon_plus_recent(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $tenant->asCurrent(function () {
            Event::factory()->open()->create(['name' => 'Evenement ouvert', 'starts_at' => now()->addWeek()]);
            Event::factory()->create(['name' => 'Brouillon plus recent', 'status' => 'draft']);
        });

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('overview.eventName', 'Evenement ouvert'));
    }

    public function test_une_preuve_recue_apparait_dans_l_activite_recente(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $tenant->asCurrent(function () {
            $registration = Registration::factory()->proofSubmitted()->create(['name' => 'Fatou Bamba']);
            PaymentProof::factory()->create(['registration_id' => $registration->id]);
        });

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('overview.recentActivity', 1)
                ->where('overview.recentActivity.0.type', 'proof_received')
                ->where('overview.recentActivity.0.name', 'Fatou Bamba'),
            );
    }

    public function test_l_apercu_d_un_locataire_ne_montre_pas_l_evenement_d_un_autre(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $tenant->asCurrent(fn () => Event::factory()->open()->create(['name' => 'Notre evenement']));

        $otherOwner = User::factory()->withTwoFactor()->create();
        $other = $this->tenantOwnedBy($otherOwner, 'Autre Association');
        $other->asCurrent(fn () => Event::factory()->open()->create(['name' => 'Evenement etranger']));

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('overview.eventName', 'Notre evenement'));
    }
}
