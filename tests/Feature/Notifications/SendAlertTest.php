<?php

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\SendAlert;
use App\Actions\Tenants\CreateTenant;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\TenantPermission;
use App\Models\NotificationPreference;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Les alertes de l'equipe (README section 5), etape 10 de « Ordre de construction ».
 *
 * Chaque type d'alerte est adresse aux membres qui detiennent la permission de traiter ce dont il
 * parle : c'est la permission, pas le nom du profil, qui decide qui est prevenu.
 */
class SendAlertTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @param  array<int, TenantPermission>  $permissions
     */
    private function memberWith(Tenant $tenant, array $permissions): User
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, $permissions);

        return $member;
    }

    private function alert(Tenant $tenant, NotificationType $type = NotificationType::ProofReceived, ?User $except = null): int
    {
        return $tenant->asCurrent(fn () => app(SendAlert::class)->toTenantMembers(
            $type,
            ['name' => 'Aya Kouassi', 'event' => 'Gala'],
            '/association-convive/events/1/proofs',
            $except,
        ));
    }

    public function test_previent_les_membres_qui_detiennent_la_permission_du_type(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $treasurer = $this->memberWith($tenant, [TenantPermission::ProofsView]);
        $reader = $this->memberWith($tenant, [TenantPermission::EventsView]);

        $this->assertSame(2, $this->alert($tenant));

        Notification::assertSentTo($owner, TenantAlert::class);
        Notification::assertSentTo($treasurer, TenantAlert::class);
        Notification::assertNotSentTo($reader, TenantAlert::class);
    }

    public function test_ne_previent_pas_l_acteur_qui_vient_de_declencher_l_alerte(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $treasurer = $this->memberWith($tenant, [TenantPermission::ProofsView]);

        $this->alert($tenant, NotificationType::ProofRejected, except: $treasurer);

        Notification::assertSentTo($owner, TenantAlert::class);
        Notification::assertNotSentTo($treasurer, TenantAlert::class);
    }

    public function test_ne_previent_jamais_les_membres_d_une_autre_organisation(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $strangerOwner = User::factory()->withTwoFactor()->create();
        $this->tenantOwnedBy($strangerOwner, 'Autre Association');

        $this->alert($tenant);

        Notification::assertSentTo($owner, TenantAlert::class);
        Notification::assertNotSentTo($strangerOwner, TenantAlert::class);
    }

    public function test_sans_organisation_active_rien_n_est_envoye(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $this->tenantOwnedBy($owner);

        $sent = app(SendAlert::class)->toTenantMembers(NotificationType::ProofReceived, [], '/x');

        $this->assertSame(0, $sent);
        Notification::assertNothingSent();
    }

    public function test_enregistre_l_alerte_dans_la_base_centrale_avec_ses_parametres(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->alert($tenant);

        $notification = $owner->notifications()->firstOrFail();

        $this->assertSame('proof_received', $notification->data['type']);
        $this->assertSame('Aya Kouassi', $notification->data['params']['name']);
        $this->assertSame('/association-convive/events/1/proofs', $notification->data['url']);
        $this->assertSame($tenant->id, $notification->data['tenant_id']);
        $this->assertSame('Association Convive', $notification->data['tenant_name']);
        $this->assertNull($notification->read_at);
    }

    public function test_par_defaut_l_alerte_n_arrive_que_dans_l_application(): void
    {
        $user = User::factory()->create();
        $notification = new TenantAlert(NotificationType::ProofReceived, [], '/x');

        $this->assertSame(['database'], $notification->via($user));
    }

    public function test_respecte_la_preference_email_seulement(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'type' => NotificationType::ProofReceived,
            'channel' => NotificationChannel::Mail,
        ]);

        $notification = new TenantAlert(NotificationType::ProofReceived, [], '/x');

        $this->assertSame(['mail'], $notification->via($user));
    }

    public function test_respecte_la_preference_application_et_email(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'type' => NotificationType::ProofReceived,
            'channel' => NotificationChannel::Both,
        ]);

        $notification = new TenantAlert(NotificationType::ProofReceived, [], '/x');

        $this->assertEqualsCanonicalizing(['database', 'mail'], $notification->via($user));
    }

    public function test_la_preference_d_un_type_ne_change_pas_les_autres(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $user->id,
            'type' => NotificationType::ProofReceived,
            'channel' => NotificationChannel::Mail,
        ]);

        $notification = new TenantAlert(NotificationType::SeatsExhausted, [], '/x');

        $this->assertSame(['database'], $notification->via($user));
    }

    public function test_le_courriel_porte_le_texte_de_l_alerte_et_le_lien(): void
    {
        $user = User::factory()->create();
        $notification = new TenantAlert(NotificationType::ProofReceived, ['name' => 'Aya Kouassi', 'event' => 'Gala'], '/association-convive/events/1/proofs');

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('Aya Kouassi', implode(' ', $mail->introLines));
        $this->assertStringContainsString('/association-convive/events/1/proofs', $mail->actionUrl);
    }

    public function test_previent_un_utilisateur_precis_sans_passer_par_une_organisation(): void
    {
        Notification::fake();

        $invitee = User::factory()->create();

        app(SendAlert::class)->toUser($invitee, NotificationType::TeamInvitationPending, ['tenant' => 'Association Convive', 'profile' => 'Tresorier'], '/settings/tenants');

        Notification::assertSentTo($invitee, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::TeamInvitationPending);
    }
}
