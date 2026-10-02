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
            'organisation_name' => 'Soldats du Palais',
            'email' => 'test@example.com',
            'phone' => '+225 07 07 12 34 56',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => 'on',
        ]);

        $this->assertAuthenticated();

        // README ecran 2 : la creation d'un espace redirige vers le formulaire d'organisation
        // (ecran 14), pas vers le tableau de bord. L'espace personnel cree a l'inscription n'a
        // ni identite legale ni sous-domaine.
        $user = User::where('email', 'test@example.com')->first();
        $tenant = $user->personalTenant();

        $response->assertRedirect(route('tenants.organisation.edit', $tenant));
    }

    public function test_l_espace_cree_porte_le_nom_de_l_organisation_et_le_telephone_est_enregistre()
    {
        $this->post(route('register.store'), [
            'name' => 'Amara Kone',
            'organisation_name' => 'Soldats du Palais',
            'email' => 'amara@example.com',
            'phone' => '+225 07 07 12 34 56',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => 'on',
        ]);

        $user = User::where('email', 'amara@example.com')->firstOrFail();

        $this->assertSame('Soldats du Palais', $user->personalTenant()->name);
        $this->assertSame('+225 07 07 12 34 56', $user->phone);
    }

    public function test_le_telephone_est_obligatoire_a_l_inscription()
    {
        $this->post(route('register.store'), [
            'name' => 'Amara Kone',
            'organisation_name' => 'Soldats du Palais',
            'email' => 'amara@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => 'on',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_sans_nom_d_organisation_l_espace_prend_le_nom_par_defaut()
    {
        // Cas de la personne invitee : elle rejoint une organisation existante, son propre espace
        // d'essai n'a pas besoin d'un nom choisi.
        $this->post(route('register.store'), [
            'name' => 'Fatou Diallo',
            'email' => 'fatou@example.com',
            'phone' => '+225 05 05 11 22 33',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => 'on',
        ]);

        $user = User::where('email', 'fatou@example.com')->firstOrFail();

        $this->assertSame(__('tenants.personal_name', ['name' => 'Fatou Diallo']), $user->personalTenant()->name);
    }
}
