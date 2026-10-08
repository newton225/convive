<?php

namespace Tests\Feature\Waitlist;

use App\Actions\Tenants\CreateTenant;
use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ajouter des places est un geste qui libere du stock, comme une annulation (decision du
 * 2026-10-08) : la liste d'attente est invitee, sans attendre une expiration ou une purge.
 */
class CapacityGrowthTest extends TestCase
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

    /**
     * @param  array<int, array{count: int, seats: int}>  $groups
     * @return array<string, mixed>
     */
    private function payload(array $groups): array
    {
        return [
            'name' => 'Diner de gala',
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'venue' => 'Hotel Ivoire',
            'table_groups' => $groups,
            'price_per_person' => 15000,
            'companion_limit' => 5,
            'hold_duration_minutes' => 10,
            'payment_accounts' => [],
        ];
    }

    /**
     * Un evenement complet, avec une personne en attente.
     *
     * @return array{event: Event, entry: WaitlistEntry}
     */
    private function fullEventWithAWaitingGuest(): array
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([['count' => 1, 'seats' => 2]]));

        return $this->tenant->asCurrent(function () {
            $event = Event::latest('id')->firstOrFail();
            Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 2]);

            return [
                'event' => $event,
                'entry' => WaitlistEntry::factory()->create(['event_id' => $event->id, 'status' => WaitlistStatus::Waiting]),
            ];
        });
    }

    public function test_ajouter_des_places_invite_la_liste_d_attente(): void
    {
        ['event' => $event, 'entry' => $entry] = $this->fullEventWithAWaitingGuest();

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.update', [$this->tenant, $event]), $this->payload([['count' => 1, 'seats' => 4]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(WaitlistStatus::Invited, $this->tenant->asCurrent(fn () => $entry->fresh()->status));
    }

    public function test_sans_place_en_plus_la_liste_d_attente_reste_en_attente(): void
    {
        ['event' => $event, 'entry' => $entry] = $this->fullEventWithAWaitingGuest();

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.update', [$this->tenant, $event]), $this->payload([['count' => 1, 'seats' => 2]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(WaitlistStatus::Waiting, $this->tenant->asCurrent(fn () => $entry->fresh()->status));
    }
}
