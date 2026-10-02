<?php

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\NotifyUpcomingPurge;
use App\Actions\Registrations\HoldRegistration;
use App\Actions\Tenants\CreateTenant;
use App\Enums\NotificationType;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Alertes d'anticipation du prototype (Convive.dc.html) : « Il reste 68 places » quand le stock
 * passe sous un seuil, et « Purge programmee dans 1 jour » avant la suppression des dossiers.
 */
class UpcomingAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    private function alertsOf(NotificationType $type): int
    {
        return Notification::sent($this->owner, TenantAlert::class)
            ->filter(fn (TenantAlert $alert) => $alert->type === $type)
            ->count();
    }

    private function hold(Event $event, int $partySize): void
    {
        $this->tenant->asCurrent(function () use ($event, $partySize) {
            $registration = Registration::factory()->create(['event_id' => $event->id, 'party_size' => $partySize]);
            app(HoldRegistration::class)->handle($event->fresh(), $registration);
        });
    }

    public function test_passer_sous_le_seuil_de_places_previent_l_equipe_une_seule_fois(): void
    {
        Notification::fake();

        // 10 tables de 4 : 40 places, seuil a 25 % soit 10 places restantes.
        $event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create(['tables' => [10, 4]]));

        $this->hold($event, 29);
        $this->assertSame(0, $this->alertsOf(NotificationType::SeatsLow));

        $this->hold($event, 1);
        $this->assertSame(1, $this->alertsOf(NotificationType::SeatsLow));

        $this->hold($event, 1);
        $this->assertSame(1, $this->alertsOf(NotificationType::SeatsLow));
    }

    public function test_une_purge_dans_moins_de_24_heures_previent_l_equipe_une_seule_fois(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create(['purge_at' => now()->addHours(12)]);
            Registration::factory()->held()->create(['event_id' => $event->id]);

            app(NotifyUpcomingPurge::class)->handle($event);
            app(NotifyUpcomingPurge::class)->handle($event->fresh());
        });

        $this->assertSame(1, $this->alertsOf(NotificationType::PurgeScheduled));
    }

    public function test_une_purge_lointaine_ou_sans_dossier_concerne_ne_previent_personne(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(function () {
            $far = Event::factory()->open()->create(['purge_at' => now()->addDays(3)]);
            Registration::factory()->held()->create(['event_id' => $far->id]);
            app(NotifyUpcomingPurge::class)->handle($far);

            $empty = Event::factory()->open()->create(['purge_at' => now()->addHours(12)]);
            app(NotifyUpcomingPurge::class)->handle($empty);
        });

        $this->assertSame(0, $this->alertsOf(NotificationType::PurgeScheduled));
    }
}
