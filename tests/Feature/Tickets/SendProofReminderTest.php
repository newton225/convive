<?php

namespace Tests\Feature\Tickets;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\SendProofReminder;
use App\Enums\ReminderCheckpoint;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Registrations\ProofReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Rappels de preuve manquante (README 2.7), etape 8 de « Ordre de construction ».
 */
class SendProofReminderTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * `signedResumeUrl()` a besoin d'un sous-domaine pour construire le lien (voir
     * `SendInvitationCardTest`).
     */
    private function publishedEvent(Tenant $tenant): Event
    {
        $tenant->update(['subdomain' => 'convive-ci']);

        return $tenant->asCurrent(fn () => Event::factory()->published()->create());
    }

    public function test_envoie_le_rappel_j7(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->held()->create(['event_id' => $event->id]));

        $sent = $tenant->asCurrent(
            fn () => app(SendProofReminder::class)->handle($registration, ReminderCheckpoint::SevenDaysBefore),
        );

        $this->assertTrue($sent);
        Notification::assertSentOnDemand(ProofReminder::class);
        $this->assertNotNull($tenant->asCurrent(fn () => $registration->fresh())->proof_reminder_j7_sent_at);
    }

    public function test_ne_renvoie_pas_le_meme_rappel_deux_fois(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->held()->create([
            'event_id' => $event->id,
            'proof_reminder_j2_sent_at' => now(),
        ]));

        $sent = $tenant->asCurrent(
            fn () => app(SendProofReminder::class)->handle($registration, ReminderCheckpoint::TwoDaysBefore),
        );

        $this->assertFalse($sent);
        Notification::assertNothingSent();
    }

    /**
     * Les trois checkpoints sont independants : avoir deja recu le rappel J-7 ne dispense pas
     * du rappel J-1.
     */
    public function test_un_checkpoint_deja_envoye_n_empeche_pas_les_autres(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->held()->create([
            'event_id' => $event->id,
            'proof_reminder_j7_sent_at' => now()->subDays(6),
        ]));

        $sent = $tenant->asCurrent(
            fn () => app(SendProofReminder::class)->handle($registration, ReminderCheckpoint::OneDayBefore),
        );

        $this->assertTrue($sent);
    }
}
