<?php

namespace Tests\Feature\Seating;

use App\Actions\Seating\MoveRegistrationToTable;
use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MoveRegistrationToTableTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function confirmedRegistration(Tenant $tenant, Event $event, array $attributes = []): Registration
    {
        return $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'party_size' => 1,
            ...$attributes,
        ]));
    }

    public function test_place_manuellement_une_inscription_non_assise(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $table = $tenant->asCurrent(fn () => SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]));
        $registration = $this->confirmedRegistration($tenant, $event);

        $assignment = $tenant->asCurrent(
            fn () => app(MoveRegistrationToTable::class)->handle($registration, $table, $owner),
        );

        $this->assertSame($table->id, $assignment->seating_table_id);
        $this->assertTrue($assignment->assigned_manually);
    }

    public function test_deplace_une_inscription_deja_assise_vers_une_autre_table(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $origin = $tenant->asCurrent(fn () => SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]));
        $destination = $tenant->asCurrent(fn () => SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]));
        $registration = $this->confirmedRegistration($tenant, $event);
        $tenant->asCurrent(fn () => RegistrationTableAssignment::create([
            'registration_id' => $registration->id,
            'seating_table_id' => $origin->id,
        ]));

        $assignment = $tenant->asCurrent(
            fn () => app(MoveRegistrationToTable::class)->handle($registration, $destination, $owner),
        );

        $this->assertSame($destination->id, $assignment->seating_table_id);
        $this->assertSame(1, $tenant->asCurrent(fn () => RegistrationTableAssignment::where('registration_id', $registration->id)->count()));
    }

    public function test_retire_une_inscription_de_sa_table(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $table = $tenant->asCurrent(fn () => SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]));
        $registration = $this->confirmedRegistration($tenant, $event);
        $tenant->asCurrent(fn () => RegistrationTableAssignment::create([
            'registration_id' => $registration->id,
            'seating_table_id' => $table->id,
        ]));

        $assignment = $tenant->asCurrent(
            fn () => app(MoveRegistrationToTable::class)->handle($registration, null, $owner),
        );

        $this->assertNull($assignment);
        $this->assertNull($tenant->asCurrent(fn () => $registration->fresh()->tableAssignment));
    }

    public function test_refuse_une_table_sans_assez_de_places_libres(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $table = $tenant->asCurrent(fn () => SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 2]));
        $alreadySeated = $this->confirmedRegistration($tenant, $event, ['party_size' => 2]);
        $tenant->asCurrent(fn () => RegistrationTableAssignment::create([
            'registration_id' => $alreadySeated->id,
            'seating_table_id' => $table->id,
        ]));
        $registration = $this->confirmedRegistration($tenant, $event, ['party_size' => 1]);

        $this->expectException(ValidationException::class);

        $tenant->asCurrent(fn () => app(MoveRegistrationToTable::class)->handle($registration, $table, $owner));
    }

    public function test_une_table_deja_assise_peut_etre_choisie_de_nouveau_pour_elle_meme(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $table = $tenant->asCurrent(fn () => SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 1]));
        $registration = $this->confirmedRegistration($tenant, $event, ['party_size' => 1]);
        $tenant->asCurrent(fn () => RegistrationTableAssignment::create([
            'registration_id' => $registration->id,
            'seating_table_id' => $table->id,
        ]));

        $assignment = $tenant->asCurrent(
            fn () => app(MoveRegistrationToTable::class)->handle($registration, $table, $owner),
        );

        $this->assertSame($table->id, $assignment->seating_table_id);
    }

    public function test_le_placement_manuel_est_journalise_avec_l_acteur(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $table = $tenant->asCurrent(fn () => SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]));
        $registration = $this->confirmedRegistration($tenant, $event);

        $tenant->asCurrent(fn () => app(MoveRegistrationToTable::class)->handle($registration, $table, $owner));

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'seating.moved')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame($table->id, $activity->properties['attributes']['seating_table_id']);
    }
}
