<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La reponse a l'inscription (`App\Http\Responses\RegisterResponse`) : ou la personne atterrit, et
 * que le bouton « retour » du navigateur ne la ramene pas au formulaire qu'elle vient d'envoyer.
 */
class RegisterResponseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Test User',
            'organisation_name' => 'Soldats du Palais',
            'email' => 'test@example.com',
            'phone' => '+225 07 07 12 34 56',
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
            'terms' => 'on',
        ];
    }

    public function test_l_inscription_vide_l_historique_du_navigateur(): void
    {
        $this->post(route('register.store'), $this->payload())->assertRedirect();

        $this->assertTrue(session()->get('inertia.clear_history'));
    }

    public function test_l_inscription_mene_au_formulaire_de_l_organisation(): void
    {
        $response = $this->post(route('register.store'), $this->payload());

        $tenant = User::where('email', 'test@example.com')->firstOrFail()->personalTenant();

        $response->assertRedirect(route('tenants.organisation.edit', $tenant));
    }

    public function test_une_requete_json_recoit_un_201_sans_double_authentification(): void
    {
        $this->postJson(route('register.store'), $this->payload())
            ->assertCreated()
            ->assertExactJson(['two_factor' => false]);
    }
}
