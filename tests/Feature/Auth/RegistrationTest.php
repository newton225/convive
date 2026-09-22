<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_registration_screen_includes_tenant_invitation_context()
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create(['name' => 'Laravel Tenant']);
        $this->joinAsOwner($tenant, $owner);

        $invitation = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this->get(route('register', ['invitation' => $invitation->code]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('tenantInvitation.code', $invitation->code)
            ->where('tenantInvitation.tenantName', 'Laravel Tenant'),
        );
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        // README ecran 2 : la creation d'un espace redirige vers le formulaire d'organisation
        // (ecran 14), pas vers le tableau de bord. L'espace personnel cree a l'inscription n'a
        // ni identite legale ni sous-domaine.
        $user = User::where('email', 'test@example.com')->first();
        $tenant = $user->personalTenant();

        $response->assertRedirect(route('tenants.organisation.edit', $tenant));
    }
}
