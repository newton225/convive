<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tables de tailles differentes (decision du 2026-09-29, README ecran 13 et 21, CLAUDE.md
 * « Evenements ») : la salle se decrit en groupes de tables, la capacite est la somme des places
 * des tables, et une table se reajuste une a une dans le plan de salle sans jamais laisser un
 * invite deja place sans chaise.
 */
class TablePlanTest extends TestCase
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

    private function event(): Event
    {
        return $this->tenant->asCurrent(fn () => Event::latest('id')->firstOrFail());
    }

    /**
     * @return array<int, int> capacite de chaque table, par numero
     */
    private function tables(Event $event): array
    {
        return $this->tenant->asCurrent(fn () => SeatingTable::where('event_id', $event->id)
            ->orderBy('number')
            ->pluck('capacity', 'number')
            ->all());
    }

    private function seat(Event $event, int $tableNumber, int $partySize): void
    {
        $this->tenant->asCurrent(function () use ($event, $tableNumber, $partySize) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => $partySize]);
            RegistrationTableAssignment::create([
                'registration_id' => $registration->id,
                'seating_table_id' => SeatingTable::where('event_id', $event->id)->where('number', $tableNumber)->value('id'),
                'assigned_manually' => true,
            ]);
        });
    }

    public function test_la_salle_se_decrit_en_groupes_de_tables_de_tailles_differentes(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.store', $this->tenant), $this->payload([
                ['count' => 2, 'seats' => 12],
                ['count' => 3, 'seats' => 8],
            ]))
            ->assertSessionHasNoErrors();

        $event = $this->event();

        // Numerotees groupe apres groupe, des l'enregistrement.
        $this->assertSame([1 => 12, 2 => 12, 3 => 8, 4 => 8, 5 => 8], $this->tables($event));
        $this->assertSame(48, $this->tenant->asCurrent(fn () => $event->fresh()->capacity()));
    }

    public function test_l_ancien_format_reste_accepte_comme_un_seul_groupe(): void
    {
        $payload = $this->payload([]);
        unset($payload['table_groups']);

        $this->actingAs($this->owner)
            ->post(route('tenants.events.store', $this->tenant), [...$payload, 'table_count' => 4, 'seats_per_table' => 10])
            ->assertSessionHasNoErrors();

        $this->assertSame([1 => 10, 2 => 10, 3 => 10, 4 => 10], $this->tables($this->event()));
    }

    public function test_un_evenement_sans_tables_creees_garde_l_ancien_calcul(): void
    {
        $event = $this->tenant->asCurrent(fn () => Event::factory()->create(['table_count' => 5, 'seats_per_table' => 6]));

        $this->assertSame(30, $this->tenant->asCurrent(fn () => $event->capacity()));
    }

    public function test_une_table_occupee_ne_se_retire_pas_du_plan(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            ['count' => 3, 'seats' => 8],
        ]));
        $event = $this->event();
        $this->seat($event, 3, 2);

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.update', [$this->tenant, $event]), $this->payload([['count' => 2, 'seats' => 8]]))
            ->assertSessionHasErrors('table_groups');

        $this->assertCount(3, $this->tables($event));
    }

    public function test_une_table_ne_descend_pas_sous_les_personnes_deja_placees(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            ['count' => 2, 'seats' => 8],
        ]));
        $event = $this->event();
        $this->seat($event, 1, 6);

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.update', [$this->tenant, $event]), $this->payload([['count' => 2, 'seats' => 5]]))
            ->assertSessionHasErrors('table_groups');

        $this->assertSame([1 => 8, 2 => 8], $this->tables($event));
    }

    public function test_une_table_vide_peut_etre_retiree_et_les_autres_redimensionnees(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            ['count' => 3, 'seats' => 8],
        ]));
        $event = $this->event();
        $this->seat($event, 1, 4);

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.update', [$this->tenant, $event]), $this->payload([['count' => 2, 'seats' => 10]]))
            ->assertSessionHasNoErrors();

        $this->assertSame([1 => 10, 2 => 10], $this->tables($event));
    }

    public function test_retirer_tous_les_groupes_vide_le_plan(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            ['count' => 2, 'seats' => 8],
        ]));
        $event = $this->event();

        // Le formulaire envoie un champ vide quand il n'a plus aucune ligne.
        $this->actingAs($this->owner)
            ->patch(route('tenants.events.update', [$this->tenant, $event]), [...$this->payload([]), 'table_groups' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $this->tables($event));
        $this->assertSame(0, $this->tenant->asCurrent(fn () => $event->fresh()->capacity()));
    }

    public function test_le_plan_de_salle_ajuste_une_table_precise(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            ['count' => 2, 'seats' => 8],
        ]));
        $event = $this->event();
        $table = $this->tenant->asCurrent(fn () => SeatingTable::where('event_id', $event->id)->where('number', 2)->firstOrFail());

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.seating.tables.update', [$this->tenant, $event, $table]), ['capacity' => 12])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame([1 => 8, 2 => 12], $this->tables($event));
        $this->assertSame(20, $this->tenant->asCurrent(fn () => $event->fresh()->capacity()));
    }

    public function test_le_plan_de_salle_refuse_une_table_plus_petite_que_ses_occupants(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            ['count' => 1, 'seats' => 8],
        ]));
        $event = $this->event();
        $this->seat($event, 1, 6);
        $table = $this->tenant->asCurrent(fn () => SeatingTable::where('event_id', $event->id)->firstOrFail());

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.seating.tables.update', [$this->tenant, $event, $table]), ['capacity' => 5])
            ->assertSessionHasErrors('capacity');

        $this->assertSame([1 => 8], $this->tables($event));
    }

    public function test_un_evenement_publie_ne_descend_pas_sous_les_places_deja_prises(): void
    {
        $event = $this->tenant->asCurrent(function () {
            $event = Event::factory()->published()->create(['table_count' => 2, 'seats_per_table' => 5]);
            SeatingTable::factory()->create(['event_id' => $event->id, 'number' => 1, 'capacity' => 5]);
            SeatingTable::factory()->create(['event_id' => $event->id, 'number' => 2, 'capacity' => 5]);
            // Confirmee mais pas encore placee : elle compte dans les places prises.
            Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 8]);

            return $event;
        });
        $table = $this->tenant->asCurrent(fn () => SeatingTable::where('event_id', $event->id)->where('number', 2)->firstOrFail());

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.seating.tables.update', [$this->tenant, $event, $table]), ['capacity' => 2])
            ->assertSessionHasErrors('capacity');

        $this->assertSame([1 => 5, 2 => 5], $this->tables($event));
    }

    public function test_seul_qui_modifie_l_evenement_ajuste_une_table(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            ['count' => 1, 'seats' => 8],
        ]));
        $event = $this->event();
        $table = $this->tenant->asCurrent(fn () => SeatingTable::where('event_id', $event->id)->firstOrFail());

        $placer = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $placer, [TenantPermission::SeatingView, TenantPermission::SeatingAssign], 'Placement');

        $this->actingAs($placer)
            ->patch(route('tenants.events.seating.tables.update', [$this->tenant, $event, $table]), ['capacity' => 12])
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_une_table(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            ['count' => 1, 'seats' => 8],
        ]));
        $event = $this->event();
        $table = $this->tenant->asCurrent(fn () => SeatingTable::where('event_id', $event->id)->firstOrFail());

        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->patch(route('tenants.events.seating.tables.update', [$this->tenant, $event, $table]), ['capacity' => 12])
            ->assertNotFound();
    }

    public function test_la_duplication_reprend_le_plan_de_salle(): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.store', $this->tenant), $this->payload([
            ['count' => 1, 'seats' => 12],
            ['count' => 2, 'seats' => 8],
        ]));
        $source = $this->event();

        $this->actingAs($this->owner)
            ->post(route('tenants.events.duplicate', [$this->tenant, $source]))
            ->assertRedirect();

        $this->assertSame([1 => 12, 2 => 8, 3 => 8], $this->tables($this->event()));
    }
}
