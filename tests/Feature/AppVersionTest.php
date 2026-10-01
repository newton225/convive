<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La version de l'application s'affiche dans le back-office. Elle n'est partagee qu'avec un membre
 * connecte : un visiteur anonyme n'a pas a savoir quelle version tourne.
 */
class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_membre_connecte_recoit_la_version_de_l_application(): void
    {
        config(['convive.version' => '2.4.1']);

        $owner = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('appVersion', '2.4.1'));
    }

    public function test_un_visiteur_anonyme_ne_recoit_pas_la_version(): void
    {
        config(['convive.version' => '2.4.1']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('appVersion', null));
    }
}
