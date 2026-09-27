<?php

namespace Tests\Feature\Auth;

use App\Actions\Tenants\CreateTenant;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expiration absolue de la session (SECURITY.md, « Deconnexion et sessions ») : au dela d'une duree
 * fixe depuis la connexion, la session prend fin meme si elle n'a jamais cesse d'etre active. Un
 * cookie de session vole cesse ainsi de servir, quoi que fasse celui qui le detient.
 */
class AbsoluteSessionLifetimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_session_recente_reste_ouverte(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($user, 'Association Convive');

        $this->actingAs($user)
            ->withSession([EnforceAbsoluteSessionLifetime::SessionKey => now()->subHour()->getTimestamp()])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertAuthenticated();
    }

    public function test_une_session_trop_ancienne_est_fermee_meme_si_elle_reste_active(): void
    {
        config(['convive.security.session_absolute_lifetime' => 720]);

        $user = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($user, 'Association Convive');

        $this->actingAs($user)
            ->withSession([EnforceAbsoluteSessionLifetime::SessionKey => now()->subMinutes(721)->getTimestamp()])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('account.session.expired'));

        $this->assertGuest();
    }

    public function test_la_connexion_pose_l_heure_de_depart_de_la_session(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertNotNull(session(EnforceAbsoluteSessionLifetime::SessionKey));
    }
}
