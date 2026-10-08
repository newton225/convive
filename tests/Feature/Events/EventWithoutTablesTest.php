<?php

namespace Tests\Feature\Events;

use App\Actions\Registrations\FinalizeConfirmedRegistration;
use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un evenement sans table, comme un rassemblement en plein air (decision du 2026-10-08) : le
 * formulaire demande un nombre de places, aucune table n'est attribuee, et la capacite sert comme
 * toujours aux places restantes, au complet et a la liste d'attente.
 */
class EventWithoutTablesTest extends TestCase
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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rassemblement au jardin',
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'venue' => 'Jardin botanique',
            'seats_at_tables' => false,
            'free_seats' => 150,
            'price_per_person' => 0,
            'companion_limit' => 5,
            'hold_duration_minutes' => 10,
            'payment_accounts' => [],
        ], $overrides);
    }

    private function event(): Event
    {
        return $this->tenant->asCurrent(fn () => Event::latest('id')->firstOrFail());
    }

    public function test_un_evenement_sans_table_a_pour_capacite_le_nombre_de_places_saisi(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.store', $this->tenant), $this->payload())
            ->assertSessionHasNoErrors();

        $event = $this->event();

        $this->assertFalse($event->seatsAtTables());
        $this->assertSame(150, $this->tenant->asCurrent(fn () => $event->capacity()));
    }

    public function test_le_nombre_de_places_est_obligatoire_sans_table(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.store', $this->tenant), $this->payload(['free_seats' => null]))
            ->assertSessionHasErrors('free_seats');

        $this->assertSame(0, $this->tenant->asCurrent(fn () => Event::count()));
    }

    public function test_les_groupes_de_tables_sont_ignores_sans_table(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.store', $this->tenant), $this->payload([
                'table_groups' => [['count' => 5, 'seats' => 10]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(150, $this->tenant->asCurrent(fn () => $this->event()->capacity()));
    }

    public function test_un_evenement_sans_table_ne_manque_pas_de_capacite_pour_publier(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload());

        $missing = $this->tenant->asCurrent(fn () => $this->event()->missingBeforePublishing());

        $this->assertNotContains('capacity', $missing);
        $this->assertNotContains('seats', $missing);
    }

    public function test_une_inscription_confirmee_n_est_assise_a_aucune_table(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload());
        $event = $this->event();

        $this->tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 2]);

            app(FinalizeConfirmedRegistration::class)->handle($registration);

            $this->assertSame(0, RegistrationTableAssignment::where('registration_id', $registration->id)->count());
            $this->assertSame(148, $event->remainingSeats());
        });
    }

    public function test_le_plan_de_salle_n_existe_pas_sans_table(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload());

        $this->actingAs($this->owner)
            ->get(route('tenants.events.seating.index', [$this->tenant, $this->event()]))
            ->assertNotFound();
    }

    public function test_repasser_a_des_tables_decrit_la_salle_en_groupes(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload());
        $event = $this->event();

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.update', [$this->tenant, $event]), $this->payload([
                'seats_at_tables' => true,
                'table_groups' => [['count' => 4, 'seats' => 10]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->tenant->asCurrent(fn () => $event->fresh()->seatsAtTables()));
        $this->assertSame(40, $this->tenant->asCurrent(fn () => $event->fresh()->capacity()));
    }

    public function test_on_ne_quitte_pas_les_tables_quand_des_invites_y_sont_assis(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            'seats_at_tables' => true,
            'table_groups' => [['count' => 3, 'seats' => 8]],
        ]));
        $event = $this->event();

        $this->tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 2]);
            RegistrationTableAssignment::create([
                'registration_id' => $registration->id,
                'seating_table_id' => SeatingTable::where('event_id', $event->id)->where('number', 1)->value('id'),
                'assigned_manually' => true,
            ]);
        });

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.update', [$this->tenant, $event]), $this->payload())
            ->assertSessionHasErrors('seats_at_tables');

        $this->assertTrue($this->tenant->asCurrent(fn () => $event->fresh()->seatsAtTables()));
    }
}
