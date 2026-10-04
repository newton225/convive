<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
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
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
            'terms' => 'on',
        ]);

        $user = User::where('email', 'amara@example.com')->firstOrFail();

        $this->assertSame('Soldats du Palais', $user->personalTenant()->name);
        // Enregistre sous sa forme unique (E.164), comme celui d'un invite : c'est elle qu'attend
        // WhatsApp pour les alertes des membres.
        $this->assertSame('+2250707123456', $user->phone);
    }

    public function test_un_numero_etranger_est_accepte_et_garde_son_indicatif()
    {
        $this->post(route('register.store'), [
            'name' => 'Amara Kone',
            'email' => 'amara@example.com',
            'phone' => '+33 6 12 34 56 78',
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
            'terms' => 'on',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+33612345678', User::where('email', 'amara@example.com')->firstOrFail()->phone);
    }

    public function test_un_numero_invalide_est_refuse_a_l_inscription()
    {
        $this->post(route('register.store'), [
            'name' => 'Amara Kone',
            'email' => 'amara@example.com',
            'phone' => '12345',
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
            'terms' => 'on',
        ])->assertSessionHasErrors('phone');

        $this->assertGuest();
    }

    public function test_l_ecran_d_inscription_propose_le_pays_du_visiteur()
    {
        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page->where('defaultCountry', 'CI'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'moins de 8 caracteres' => ['Co-26!a'],
            'sans majuscule' => ['convive-2026!'],
            'sans minuscule' => ['CONVIVE-2026!'],
            'sans chiffre' => ['Convive-deux!'],
            'sans symbole' => ['Convive2026'],
        ];
    }

    /**
     * Les memes regles partout, developpement compris (decision du proprietaire du projet,
     * 2026-10-04) : 8 caracteres, majuscule et minuscule, chiffre, symbole.
     */
    #[DataProvider('weakPasswords')]
    public function test_un_mot_de_passe_trop_faible_est_refuse(string $password)
    {
        $this->post(route('register.store'), [
            'name' => 'Amara Kone',
            'email' => 'amara@example.com',
            'phone' => '+225 07 07 12 34 56',
            'password' => $password,
            'password_confirmation' => $password,
            'terms' => 'on',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }

    public function test_l_ecran_d_inscription_recoit_les_regles_du_mot_de_passe()
    {
        $this->get(route('register'))->assertInertia(fn (Assert $page) => $page
            ->where('passwordPolicy.min', 8)
            ->where('passwordPolicy.mixedCase', true)
            ->where('passwordPolicy.numbers', true)
            ->where('passwordPolicy.symbols', true));
    }

    public function test_le_telephone_est_obligatoire_a_l_inscription()
    {
        $this->post(route('register.store'), [
            'name' => 'Amara Kone',
            'organisation_name' => 'Soldats du Palais',
            'email' => 'amara@example.com',
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
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
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
            'terms' => 'on',
        ]);

        $user = User::where('email', 'fatou@example.com')->firstOrFail();

        $this->assertSame(__('tenants.personal_name', ['name' => 'Fatou Diallo']), $user->personalTenant()->name);
    }
}
