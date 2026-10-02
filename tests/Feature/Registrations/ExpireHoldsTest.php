<?php

namespace Tests\Feature\Registrations;

use App\Actions\Registrations\ExpireHolds;
use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expiration des reservations dont le decompte est ecoule (README 2.1 et 2.2), extraite de la
 * tache planifiee pour etre testable, etape 10 (elle previent aussi l'equipe).
 */
class ExpireHoldsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create(['tables' => [1, 2]]));
    }

    public function test_marque_expirees_les_reservations_dont_le_decompte_est_ecoule(): void
    {
        $this->tenant->asCurrent(function () {
            $late = Registration::factory()->create(['event_id' => $this->event->id, 'status' => RegistrationStatus::Held, 'held_until' => now()->subMinute()]);
            $running = Registration::factory()->held()->create(['event_id' => $this->event->id]);

            $count = app(ExpireHolds::class)->handle($this->event);

            $this->assertSame(1, $count);
            $this->assertSame(RegistrationStatus::Expired, $late->fresh()->status);
            $this->assertSame(RegistrationStatus::Held, $running->fresh()->status);
        });
    }

    public function test_ignore_les_reservations_d_un_autre_evenement(): void
    {
        $this->tenant->asCurrent(function () {
            $other = Event::factory()->open()->create();
            $foreign = Registration::factory()->create(['event_id' => $other->id, 'status' => RegistrationStatus::Held, 'held_until' => now()->subMinute()]);

            $this->assertSame(0, app(ExpireHolds::class)->handle($this->event));
            $this->assertSame(RegistrationStatus::Held, $foreign->fresh()->status);
        });
    }

    public function test_avance_la_liste_d_attente_quand_une_place_se_libere(): void
    {
        $this->tenant->asCurrent(function () {
            Registration::factory()->create([
                'event_id' => $this->event->id,
                'status' => RegistrationStatus::Held,
                'held_until' => now()->subMinute(),
                'party_size' => 2,
            ]);
            $entry = WaitlistEntry::factory()->create(['event_id' => $this->event->id, 'party_size' => 2]);

            app(ExpireHolds::class)->handle($this->event);

            $this->assertSame(WaitlistStatus::Invited, $entry->fresh()->status);
        });
    }
}
