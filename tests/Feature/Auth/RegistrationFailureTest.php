<?php

namespace Tests\Feature\Auth;

use App\Actions\Tenants\CreateStarterUnits;
use App\Models\User;
use App\Notifications\Console\TenantCreationFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * La creation d'un compte dont l'espace ne peut pas etre ouvert (incident du 2026-10-04) : un message
 * clair sur le formulaire plutot qu'une page d'erreur, et l'equipe prevenue par courriel.
 */
class RegistrationFailureTest extends TestCase
{
    use RefreshDatabase;

    private function register(): TestResponse
    {
        return $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Amara Kone',
            'organisation_name' => 'Soldats du Palais',
            'email' => 'amara@example.com',
            'phone' => '+225 07 07 12 34 56',
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
            'terms' => 'on',
        ]);
    }

    public function test_l_echec_de_l_espace_s_affiche_clairement_et_rien_n_est_garde(): void
    {
        Notification::fake();
        config(['convive.alert_email' => 'alertes@convive.test']);
        $this->mock(CreateStarterUnits::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('panne simulee')));

        $this->register()
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('registration');

        $this->assertGuest();
        $this->assertSame(0, User::where('email', 'amara@example.com')->count());

        Notification::assertSentOnDemand(
            TenantCreationFailed::class,
            fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routeNotificationFor('mail') === 'alertes@convive.test',
        );
    }

    public function test_sans_adresse_d_alerte_le_message_s_affiche_quand_meme(): void
    {
        Notification::fake();
        config(['convive.alert_email' => null]);
        $this->mock(CreateStarterUnits::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('panne simulee')));

        $this->register()->assertSessionHasErrors('registration');

        Notification::assertNothingSent();
    }
}
