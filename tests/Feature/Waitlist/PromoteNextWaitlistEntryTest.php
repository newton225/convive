<?php

namespace Tests\Feature\Waitlist;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Waitlist\PromoteNextWaitlistEntry;
use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoteNextWaitlistEntryTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function eventOf(Tenant $tenant, array $attributes = []): Event
    {
        return $tenant->asCurrent(fn () => Event::factory()->open()->create($attributes));
    }

    public function test_invite_le_premier_arrive_quand_une_place_est_libre(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['table_count' => 1, 'seats_per_table' => 1]);

        [$first, $second] = $tenant->asCurrent(fn () => [
            WaitlistEntry::factory()->create(['event_id' => $event->id]),
            WaitlistEntry::factory()->create(['event_id' => $event->id]),
        ]);

        $promoted = $tenant->asCurrent(fn () => app(PromoteNextWaitlistEntry::class)->handle($event));

        $this->assertTrue($promoted->is($first));

        $tenant->asCurrent(function () use ($first, $second) {
            $this->assertSame(WaitlistStatus::Invited, $first->fresh()->status);
            $this->assertSame(WaitlistStatus::Waiting, $second->fresh()->status);
            $this->assertNotNull($first->fresh()->expires_at);
        });
    }

    public function test_n_invite_personne_quand_l_evenement_reste_complet(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['table_count' => 1, 'seats_per_table' => 1]);

        $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'party_size' => 1,
        ]));

        $tenant->asCurrent(fn () => WaitlistEntry::factory()->create(['event_id' => $event->id]));

        $promoted = $tenant->asCurrent(fn () => app(PromoteNextWaitlistEntry::class)->handle($event));

        $this->assertNull($promoted);
    }

    public function test_n_invite_personne_quand_la_liste_est_vide(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['table_count' => 1, 'seats_per_table' => 1]);

        $promoted = $tenant->asCurrent(fn () => app(PromoteNextWaitlistEntry::class)->handle($event));

        $this->assertNull($promoted);
    }

    public function test_n_invite_pas_deux_fois_la_meme_entree_deja_invitee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['table_count' => 2, 'seats_per_table' => 1]);

        $entry = $tenant->asCurrent(fn () => WaitlistEntry::factory()->invited()->create(['event_id' => $event->id]));

        $promoted = $tenant->asCurrent(fn () => app(PromoteNextWaitlistEntry::class)->handle($event));

        $this->assertNull($promoted);
    }
}
