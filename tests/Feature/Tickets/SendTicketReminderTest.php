<?php

namespace Tests\Feature\Tickets;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Actions\Tickets\SendTicketReminder;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Registrations\TicketReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Rappel jour J moins 3 heures aux billets valides (README 2.7), etape 8 de « Ordre de
 * construction ».
 */
class SendTicketReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `signedResumeUrl()` a besoin d'un sous-domaine pour construire le lien (voir
     * `SendInvitationCardTest`).
     */
    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        $tenant = app(CreateTenant::class)->handle($user, $name);
        $tenant->update(['subdomain' => 'convive-ci']);

        return $tenant;
    }

    public function test_envoie_le_rappel_pour_un_billet_valide(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $ticket = $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create();
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);

            return app(IssueTicket::class)->handle($registration);
        });

        $sent = $tenant->asCurrent(fn () => app(SendTicketReminder::class)->handle($ticket));

        $this->assertTrue($sent);
        Notification::assertSentOnDemand(TicketReminder::class);
        $this->assertNotNull($tenant->asCurrent(fn () => $ticket->fresh())->reminder_sent_at);
    }

    public function test_ne_renvoie_pas_un_rappel_deja_envoye(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $ticket = $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create();
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            $ticket = app(IssueTicket::class)->handle($registration);
            $ticket->update(['reminder_sent_at' => now()]);

            return $ticket;
        });

        $sent = $tenant->asCurrent(fn () => app(SendTicketReminder::class)->handle($ticket));

        $this->assertFalse($sent);
        Notification::assertNothingSent();
    }
}
