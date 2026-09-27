<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * File des preuves alignee sur le prototype (Convive.dc.html) : chaque preuve montre les
 * accompagnateurs de l'inscription avec leur unite, pour verifier le montant d'un coup d'oeil.
 */
class ProofQueueDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_preuve_liste_les_accompagnateurs_et_leur_unite(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $event = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id, 'party_size' => 2]);
            $registration->companions()->create([
                'name' => 'Zadi Yves',
                'unit_id' => Unit::where('name', 'Aucune')->value('id'),
                'position' => 1,
            ]);
            PaymentProof::factory()->create(['registration_id' => $registration->id]);

            return $event;
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.companions.0.name', 'Zadi Yves')
                ->where('rows.0.companions.0.unit', 'Aucune'));
    }
}
