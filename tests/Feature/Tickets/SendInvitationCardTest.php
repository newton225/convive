<?php

namespace Tests\Feature\Tickets;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\SendInvitationCard;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Registrations\InvitationCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Envoi de la carte d'invitation (README 2.7, ecran 7), etape 8 de « Ordre de construction ».
 */
class SendInvitationCardTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * `signedResumeUrl()` a besoin d'un sous-domaine pour construire le lien (meme raison que
     * `Event::publicUrl()`) : un evenement reellement publiable en a toujours un
     * (`Tenant::isReadyToPublish()`), la fixture doit donc en poser un aussi.
     */
    private function publishedEvent(Tenant $tenant): Event
    {
        $tenant->update(['subdomain' => 'convive-ci']);

        return $tenant->asCurrent(fn () => Event::factory()->published()->create());
    }

    public function test_envoie_la_carte_par_whatsapp_et_par_email(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->publishedEvent($tenant);

        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'email' => 'invite@example.com',
            'phone' => '+225 07 00 00 00 00',
        ]));

        $sent = $tenant->asCurrent(fn () => app(SendInvitationCard::class)->handle($registration));

        $this->assertTrue($sent);

        Notification::assertSentOnDemand(
            InvitationCard::class,
            fn (InvitationCard $notification, array $channels, $notifiable) => in_array('mail', $channels, true)
                && in_array('whatsapp', $channels, true)
                && $notifiable->routeNotificationFor('mail') === 'invite@example.com'
                && $notifiable->routeNotificationFor('whatsapp') === '+225 07 00 00 00 00',
        );

        $this->assertNotNull($tenant->asCurrent(fn () => $registration->fresh())->card_sent_at);
    }

    public function test_envoie_uniquement_par_whatsapp_quand_l_invite_n_a_pas_fourni_d_email(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->publishedEvent($tenant);

        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->withoutEmail()->create([
            'event_id' => $event->id,
        ]));

        $tenant->asCurrent(fn () => app(SendInvitationCard::class)->handle($registration));

        Notification::assertSentOnDemand(
            InvitationCard::class,
            fn (InvitationCard $notification, array $channels) => $channels === ['whatsapp'],
        );
    }

    public function test_ne_renvoie_pas_une_carte_deja_envoyee(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->publishedEvent($tenant);

        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'card_sent_at' => now(),
        ]));

        $sent = $tenant->asCurrent(fn () => app(SendInvitationCard::class)->handle($registration));

        $this->assertFalse($sent);
        Notification::assertNothingSent();
    }

    public function test_n_envoie_rien_pour_une_inscription_non_confirmee(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->publishedEvent($tenant);

        $registration = $tenant->asCurrent(fn () => Registration::factory()->held()->create(['event_id' => $event->id]));

        $sent = $tenant->asCurrent(fn () => app(SendInvitationCard::class)->handle($registration));

        $this->assertFalse($sent);
        Notification::assertNothingSent();
    }
}
