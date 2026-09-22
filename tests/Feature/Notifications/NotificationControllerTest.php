<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La cloche (compteur de non-lus, clic vers l'ecran concerne, « tout marquer comme lu ») et les
 * preferences de canal par type d'alerte (README section 5 et ecran 25), etape 10.
 */
class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function alertFor(User $user, string $url = '/association-convive/events/1/proofs'): string
    {
        $user->notify(new TenantAlert(NotificationType::ProofReceived, ['name' => 'Aya Kouassi', 'event' => 'Gala'], $url, tenantName: 'Association Convive'));

        return $user->notifications()->latest('created_at')->firstOrFail()->id;
    }

    public function test_la_cloche_recoit_le_compteur_de_non_lus_et_les_dernieres_alertes(): void
    {
        $user = User::factory()->create();
        $this->alertFor($user);
        $this->alertFor($user);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unreadCount', 2)
                ->has('notifications.latest', 2)
                ->where('notifications.latest.0.title', fn (string $title) => str_contains($title, 'Aya Kouassi'))
                ->where('notifications.latest.0.read', false)
                ->where('notifications.latest.0.tenantName', 'Association Convive'),
            );
    }

    public function test_la_cloche_ne_montre_que_les_alertes_de_l_utilisateur_connecte(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->alertFor($other);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->where('notifications.unreadCount', 0)->has('notifications.latest', 0));
    }

    public function test_la_cloche_est_limitee_aux_dix_dernieres_alertes_mais_compte_toutes_les_non_lues(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 12) as $unused) {
            $this->alertFor($user);
        }

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->where('notifications.unreadCount', 12)->has('notifications.latest', 10));
    }

    public function test_ouvrir_une_alerte_la_marque_lue_et_renvoie_vers_l_ecran_concerne(): void
    {
        $user = User::factory()->create();
        $id = $this->alertFor($user, '/association-convive/events/1/proofs');

        $this->actingAs($user)
            ->post(route('notifications.read', $id))
            ->assertRedirect('/association-convive/events/1/proofs');

        $this->assertNotNull($user->notifications()->find($id)->read_at);
    }

    public function test_l_alerte_d_un_autre_utilisateur_recoit_404(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->alertFor($other);

        $this->actingAs($user)
            ->post(route('notifications.read', $id))
            ->assertNotFound();

        $this->assertNull($other->notifications()->find($id)->read_at);
    }

    public function test_tout_marquer_comme_lu_ne_touche_que_ses_propres_alertes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->alertFor($user);
        $this->alertFor($user);
        $this->alertFor($other);

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_un_visiteur_non_connecte_est_renvoye_vers_la_connexion(): void
    {
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
        $this->get(route('notification-preferences.edit'))->assertRedirect(route('login'));
    }

    public function test_la_page_des_preferences_liste_chaque_type_avec_son_canal_par_defaut(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notification-preferences.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/notifications')
                ->has('preferences', count(NotificationType::cases()))
                ->where('preferences.0.channel', 'app'),
            );
    }

    public function test_la_page_des_preferences_reflete_le_choix_enregistre(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'type' => NotificationType::SeatsExhausted,
            'channel' => NotificationChannel::Both,
        ]);

        $this->actingAs($user)
            ->get(route('notification-preferences.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('preferences', fn ($preferences) => collect($preferences)->firstWhere('type', 'seats_exhausted')['channel'] === 'both'),
            );
    }

    public function test_enregistre_les_canaux_choisis_par_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('notification-preferences.update'), [
                'preferences' => [
                    'proof_received' => 'both',
                    'ticket_refused' => 'mail',
                ],
            ])
            ->assertRedirect();

        $this->assertSame(NotificationChannel::Both, $user->notificationChannelFor(NotificationType::ProofReceived));
        $this->assertSame(NotificationChannel::Mail, $user->notificationChannelFor(NotificationType::TicketRefused));
        $this->assertSame(NotificationChannel::App, $user->notificationChannelFor(NotificationType::SeatsExhausted));
    }

    public function test_modifier_un_choix_met_a_jour_la_ligne_sans_en_creer_une_seconde(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('notification-preferences.update'), ['preferences' => ['proof_received' => 'mail']]);
        $this->actingAs($user)->patch(route('notification-preferences.update'), ['preferences' => ['proof_received' => 'both']]);

        $this->assertSame(1, NotificationPreference::where('user_id', $user->id)->where('type', 'proof_received')->count());
        $this->assertSame(NotificationChannel::Both, $user->notificationChannelFor(NotificationType::ProofReceived));
    }

    public function test_un_canal_inconnu_est_refuse(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('notification-preferences.update'), ['preferences' => ['proof_received' => 'sms']])
            ->assertSessionHasErrors('preferences.proof_received');
    }

    public function test_un_type_inconnu_est_refuse(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('notification-preferences.update'), ['preferences' => ['type_invente' => 'mail']])
            ->assertSessionHasErrors('preferences');

        $this->assertSame(0, NotificationPreference::count());
    }

    public function test_les_preferences_d_un_utilisateur_ne_touchent_pas_celles_d_un_autre(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patch(route('notification-preferences.update'), ['preferences' => ['proof_received' => 'mail']]);

        $this->assertSame(NotificationChannel::App, $other->notificationChannelFor(NotificationType::ProofReceived));
    }
}
