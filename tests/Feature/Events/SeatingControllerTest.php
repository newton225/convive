<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitSeparationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @return array{event: Event, table: SeatingTable, registration: Registration}
     */
    private function eventWithTableAndRegistration(Tenant $tenant): array
    {
        return $tenant->asCurrent(function () {
            // Sans table posee par la fabrique : le test pose la sienne.
            $event = Event::factory()->open()->create(['tables' => null]);
            $table = SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]);
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]);

            return ['event' => $event, 'table' => $table, 'registration' => $registration];
        });
    }

    public function test_un_membre_avec_la_permission_voit_le_plan_de_salle(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->eventWithTableAndRegistration($tenant);

        $this->actingAs($owner)
            ->get(route('tenants.events.seating.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/seating')
                ->has('tables', 1)
                ->has('unseated', 1),
            );
    }

    public function test_un_membre_sans_la_permission_de_lecture_ne_voit_pas_le_plan_de_salle(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->eventWithTableAndRegistration($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.seating.index', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_plan_de_salle(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->eventWithTableAndRegistration($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.seating.index', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_un_membre_avec_la_permission_place_une_inscription(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'table' => $table, 'registration' => $registration] = $this->eventWithTableAndRegistration($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::SeatingView, TenantPermission::SeatingAssign]);

        $this->actingAs($member)
            ->post(route('tenants.events.seating.assign', [$tenant, $event, $registration]), [
                'seating_table_id' => $table->id,
            ])
            ->assertRedirect(route('tenants.events.seating.index', [$tenant, $event]));

        $this->assertSame(
            $table->id,
            $tenant->asCurrent(fn () => $registration->fresh()->tableAssignment)->seating_table_id,
        );
    }

    public function test_un_membre_sans_la_permission_d_attribution_ne_place_rien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'table' => $table, 'registration' => $registration] = $this->eventWithTableAndRegistration($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::SeatingView]);

        $this->actingAs($member)
            ->post(route('tenants.events.seating.assign', [$tenant, $event, $registration]), [
                'seating_table_id' => $table->id,
            ])
            ->assertForbidden();

        $this->assertNull($tenant->asCurrent(fn () => $registration->fresh()->tableAssignment));
    }

    public function test_retire_une_inscription_de_sa_table_quand_aucune_n_est_soumise(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'table' => $table, 'registration' => $registration] = $this->eventWithTableAndRegistration($tenant);
        $tenant->asCurrent(fn () => RegistrationTableAssignment::create([
            'registration_id' => $registration->id,
            'seating_table_id' => $table->id,
        ]));

        $this->actingAs($owner)
            ->post(route('tenants.events.seating.assign', [$tenant, $event, $registration]), [
                'seating_table_id' => null,
            ])
            ->assertRedirect(route('tenants.events.seating.index', [$tenant, $event]));

        $this->assertNull($tenant->asCurrent(fn () => $registration->fresh()->tableAssignment));
    }

    public function test_une_table_d_un_autre_evenement_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'registration' => $registration] = $this->eventWithTableAndRegistration($tenant);
        $otherEvent = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $otherTable = $tenant->asCurrent(fn () => SeatingTable::factory()->create(['event_id' => $otherEvent->id, 'capacity' => 4]));

        $this->actingAs($owner)
            ->post(route('tenants.events.seating.assign', [$tenant, $event, $registration]), [
                'seating_table_id' => $otherTable->id,
            ])
            ->assertInvalid(['seating_table_id']);
    }

    public function test_une_inscription_d_un_autre_evenement_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['table' => $table, 'registration' => $registration] = $this->eventWithTableAndRegistration($tenant);
        $otherEvent = $tenant->asCurrent(fn () => Event::factory()->open()->create());

        $this->actingAs($owner)
            ->post(route('tenants.events.seating.assign', [$tenant, $otherEvent, $registration]), [
                'seating_table_id' => $table->id,
            ])
            ->assertNotFound();
    }

    public function test_un_membre_avec_la_permission_ajoute_une_contrainte_de_separation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        [$event, $unitA, $unitB] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $unitA = Unit::factory()->create();
            $unitB = Unit::factory()->create();

            return [$event, $unitA, $unitB];
        });

        $this->actingAs($owner)
            ->post(route('tenants.events.seating.constraints.store', [$tenant, $event]), [
                'unit_id' => $unitA->id,
                'other_unit_id' => $unitB->id,
            ])
            ->assertRedirect(route('tenants.events.seating.index', [$tenant, $event]));

        $exists = $tenant->asCurrent(
            fn () => UnitSeparationRule::where('event_id', $event->id)->exists(),
        );

        $this->assertTrue($exists);
    }

    public function test_la_meme_unite_des_deux_cotes_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        [$event, $unit] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $unit = Unit::factory()->create();

            return [$event, $unit];
        });

        $this->actingAs($owner)
            ->post(route('tenants.events.seating.constraints.store', [$tenant, $event]), [
                'unit_id' => $unit->id,
                'other_unit_id' => $unit->id,
            ])
            ->assertInvalid(['unit_id']);
    }

    public function test_la_paire_est_canonisee_quel_que_soit_l_ordre_choisi(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        [$event, $unitA, $unitB] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $unitA = Unit::factory()->create();
            $unitB = Unit::factory()->create();

            return [$event, $unitA, $unitB];
        });

        $this->actingAs($owner)->post(route('tenants.events.seating.constraints.store', [$tenant, $event]), [
            'unit_id' => $unitA->id,
            'other_unit_id' => $unitB->id,
        ]);

        $this->actingAs($owner)->post(route('tenants.events.seating.constraints.store', [$tenant, $event]), [
            'unit_id' => $unitB->id,
            'other_unit_id' => $unitA->id,
        ]);

        $count = $tenant->asCurrent(
            fn () => UnitSeparationRule::where('event_id', $event->id)->count(),
        );

        $this->assertSame(1, $count);
    }

    public function test_un_membre_sans_la_permission_d_attribution_ne_gere_pas_les_contraintes(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        [$event, $unitA, $unitB] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $unitA = Unit::factory()->create();
            $unitB = Unit::factory()->create();

            return [$event, $unitA, $unitB];
        });

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::SeatingView]);

        $this->actingAs($member)
            ->post(route('tenants.events.seating.constraints.store', [$tenant, $event]), [
                'unit_id' => $unitA->id,
                'other_unit_id' => $unitB->id,
            ])
            ->assertForbidden();
    }

    public function test_un_membre_avec_la_permission_retire_une_contrainte(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        [$event, $rule] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $unitA = Unit::factory()->create();
            $unitB = Unit::factory()->create();
            $rule = UnitSeparationRule::factory()->create([
                'event_id' => $event->id,
                'unit_a_id' => $unitA->id,
                'unit_b_id' => $unitB->id,
            ]);

            return [$event, $rule];
        });

        $this->actingAs($owner)
            ->delete(route('tenants.events.seating.constraints.destroy', [$tenant, $event, $rule]))
            ->assertRedirect(route('tenants.events.seating.index', [$tenant, $event]));

        $exists = $tenant->asCurrent(fn () => UnitSeparationRule::find($rule->id) !== null);

        $this->assertFalse($exists);
    }

    public function test_une_contrainte_d_un_autre_evenement_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        [$event, $otherEvent, $rule] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $otherEvent = Event::factory()->open()->create();
            $unitA = Unit::factory()->create();
            $unitB = Unit::factory()->create();
            $rule = UnitSeparationRule::factory()->create([
                'event_id' => $otherEvent->id,
                'unit_a_id' => $unitA->id,
                'unit_b_id' => $unitB->id,
            ]);

            return [$event, $otherEvent, $rule];
        });

        $this->actingAs($owner)
            ->delete(route('tenants.events.seating.constraints.destroy', [$tenant, $event, $rule]))
            ->assertNotFound();
    }
}
