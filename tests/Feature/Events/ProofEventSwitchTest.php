<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Plusieurs evenements avec des preuves a verifier (demande du proprietaire du projet, 2026-10-10) :
 * le bouton du tableau de bord annonce le total, et la file de preuves permet de passer d'un
 * evenement a l'autre.
 */
class ProofEventSwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $gala;

    private Event $seminar;

    private Event $quiet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        [$this->gala, $this->seminar, $this->quiet] = $this->tenant->asCurrent(function () {
            $events = [
                Event::factory()->open()->create(['name' => 'Diner de gala', 'starts_at' => now()->addWeek()]),
                Event::factory()->open()->create(['name' => 'Seminaire', 'starts_at' => now()->addMonth()]),
                Event::factory()->open()->create(['name' => 'Soiree calme', 'starts_at' => now()->addMonths(2)]),
            ];

            $this->submitProofs($events[0], 1);
            $this->submitProofs($events[1], 2);

            return $events;
        });
    }

    private function submitProofs(Event $event, int $count): void
    {
        Registration::factory()->proofSubmitted()->count($count)->create(['event_id' => $event->id])
            ->each(fn (Registration $registration) => PaymentProof::factory()->create(['registration_id' => $registration->id]));
    }

    /**
     * @return TestResponse<Response>
     */
    private function dashboard(?int $eventId = null): TestResponse
    {
        $parameters = ['current_tenant' => $this->tenant->slug, ...($eventId === null ? [] : ['event' => $eventId])];

        return $this->actingAs($this->owner)->get(route('dashboard', $parameters));
    }

    /**
     * @return TestResponse<Response>
     */
    private function queue(Event $event): TestResponse
    {
        return $this->actingAs($this->owner)->get(route('tenants.events.proofs.index', [$this->tenant, $event]));
    }

    public function test_le_bouton_du_tableau_de_bord_annonce_le_total_des_preuves_de_tous_les_evenements(): void
    {
        $this->dashboard()->assertInertia(fn (Assert $page) => $page
            ->where('proofsToCheck.total', 3),
        );
    }

    public function test_le_bouton_mene_a_l_evenement_affiche_quand_il_a_des_preuves(): void
    {
        $this->dashboard($this->seminar->id)->assertInertia(fn (Assert $page) => $page
            ->where('proofsToCheck.eventId', $this->seminar->id),
        );
    }

    public function test_le_bouton_mene_au_premier_evenement_avec_des_preuves_quand_l_evenement_affiche_n_en_a_pas(): void
    {
        $this->dashboard($this->quiet->id)->assertInertia(fn (Assert $page) => $page
            ->where('proofsToCheck.total', 3)
            ->where('proofsToCheck.eventId', $this->gala->id),
        );
    }

    public function test_le_bouton_n_a_ni_total_ni_cible_quand_aucune_preuve_n_attend(): void
    {
        $this->tenant->asCurrent(fn () => PaymentProof::query()->delete());

        $this->dashboard()->assertInertia(fn (Assert $page) => $page
            ->where('proofsToCheck.total', 0)
            ->where('proofsToCheck.eventId', null),
        );
    }

    public function test_la_file_propose_les_evenements_qui_ont_des_preuves_avec_leur_nombre(): void
    {
        $this->queue($this->gala)->assertInertia(fn (Assert $page) => $page
            ->has('eventSwitcher', 2)
            ->where('eventSwitcher.0.id', $this->gala->id)
            ->where('eventSwitcher.0.proofsToCheck', 1)
            ->where('eventSwitcher.1.id', $this->seminar->id)
            ->where('eventSwitcher.1.proofsToCheck', 2),
        );
    }

    public function test_la_file_garde_l_evenement_ouvert_meme_sans_preuve(): void
    {
        $this->queue($this->quiet)->assertInertia(fn (Assert $page) => $page
            ->has('eventSwitcher', 3)
            ->where('eventSwitcher.2.id', $this->quiet->id)
            ->where('eventSwitcher.2.proofsToCheck', 0),
        );
    }

    public function test_une_inscription_sans_preuve_deposee_n_est_pas_comptee(): void
    {
        $this->tenant->asCurrent(fn () => Registration::factory()->proofSubmitted()->create(['event_id' => $this->quiet->id]));

        $this->queue($this->gala)->assertInertia(fn (Assert $page) => $page
            ->has('eventSwitcher', 2),
        );
    }

    public function test_les_preuves_d_une_autre_organisation_ne_sont_pas_proposees(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();
        $other = app(CreateTenant::class)->handle($stranger, 'Autre organisation');
        $other->asCurrent(function () {
            $event = Event::factory()->open()->create(['name' => 'Evenement etranger']);
            $this->submitProofs($event, 4);
        });

        $this->queue($this->gala)->assertInertia(fn (Assert $page) => $page
            ->has('eventSwitcher', 2)
            ->where('eventSwitcher.0.name', 'Diner de gala'),
        );
        $this->dashboard()->assertInertia(fn (Assert $page) => $page->where('proofsToCheck.total', 3));
    }

    public function test_un_acces_du_support_limite_a_un_evenement_ne_voit_que_celui_ci(): void
    {
        $operator = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test', 'support_available' => true]);
        config(['convive.console.operators' => ['support@convive.test']]);
        SupportAccessGrant::create([
            'tenant_id' => $this->tenant->id,
            'operator_id' => $operator->id,
            'granted_by_id' => $this->owner->id,
            'expires_at' => now()->addHours(4),
            'event_id' => $this->gala->id,
            'event_name' => $this->gala->name,
        ]);

        $this->actingAs($operator)
            ->get(route('tenants.events.proofs.index', [$this->tenant, $this->gala]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('eventSwitcher', 1)
                ->where('eventSwitcher.0.id', $this->gala->id),
            );
    }
}
