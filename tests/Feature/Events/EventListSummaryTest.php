<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Les cartes de « Mes evenements » (README ecran 12) : statut, remplissage, montant collecte et
 * preuves en attente, pour juger d'un coup d'oeil ou agir.
 */
class EventListSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_carte_porte_remplissage_montant_collecte_et_preuves_en_attente(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create(['table_count' => 2, 'seats_per_table' => 5]);
            Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 3, 'amount_due' => 45000]);
            Registration::factory()->create(['event_id' => $event->id, 'party_size' => 2, 'amount_due' => 30000, 'status' => RegistrationStatus::ProofSubmitted]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.index', $tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.0.capacity', 10)
                ->where('events.0.occupiedSeats', 3)
                ->where('events.0.collectedAmount', 45000)
                ->where('events.0.proofsToCheck', 1)
                ->where('events.0.visualUrl', null));
    }
}
