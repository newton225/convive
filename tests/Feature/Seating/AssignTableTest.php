<?php

namespace Tests\Feature\Seating;

use App\Actions\Seating\AssignTable;
use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitSeparationRule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignTableTest extends TestCase
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
        return $tenant->asCurrent(fn () => Event::factory()->open()->create([
            'tables' => [2, 4],
            ...$attributes,
        ]));
    }

    private function unitNamed(Tenant $tenant, string $name): Unit
    {
        return $tenant->asCurrent(fn () => Unit::where('name', $name)->firstOrFail());
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

    public function test_attribue_une_table_disponible_a_une_inscription_confirmee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $this->confirmedRegistration($tenant, $event);

        $assignment = $tenant->asCurrent(fn () => app(AssignTable::class)->handle($registration));

        $this->assertNotNull($assignment);
        $this->assertFalse($assignment->assigned_manually);
        $this->assertSame(
            $assignment->seating_table_id,
            $tenant->asCurrent(fn () => $registration->fresh()->tableAssignment)->seating_table_id,
        );
    }

    /**
     * README 2.6 : les tables existent des l'enregistrement de l'evenement (`SyncSeatingTables`).
     * L'attribution choisit parmi elles, elle n'en cree jamais.
     */
    public function test_l_attribution_ne_cree_aucune_table(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['tables' => [3, 4]]);
        $registration = $this->confirmedRegistration($tenant, $event);

        $this->assertSame(3, $tenant->asCurrent(fn () => SeatingTable::where('event_id', $event->id)->count()));

        $tenant->asCurrent(fn () => app(AssignTable::class)->handle($registration));

        $this->assertSame(3, $tenant->asCurrent(fn () => SeatingTable::where('event_id', $event->id)->count()));
    }

    public function test_priorite_a_la_table_reservee_pour_l_unite(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        // Sans table posee par la fabrique : le test pose les siennes.
        $event = $this->eventOf($tenant, ['tables' => null]);
        $etatMajor = $this->unitNamed($tenant, 'ETAT MAJOR');

        $reserved = $tenant->asCurrent(fn () => SeatingTable::factory()->create([
            'event_id' => $event->id,
            'number' => 1,
            'capacity' => 4,
            'reserved_unit_id' => $etatMajor->id,
        ]));
        $tenant->asCurrent(fn () => SeatingTable::factory()->create([
            'event_id' => $event->id,
            'number' => 2,
            'capacity' => 4,
        ]));

        $registration = $this->confirmedRegistration($tenant, $event, ['unit_id' => $etatMajor->id]);

        $assignment = $tenant->asCurrent(fn () => app(AssignTable::class)->handle($registration));

        $this->assertSame($reserved->id, $assignment->seating_table_id);
    }

    public function test_une_table_reservee_pour_une_autre_unite_n_est_jamais_choisie(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['tables' => null]);
        $etatMajor = $this->unitNamed($tenant, 'ETAT MAJOR');
        $qodesh = $this->unitNamed($tenant, 'QODESH');

        $tenant->asCurrent(fn () => SeatingTable::factory()->create([
            'event_id' => $event->id,
            'number' => 1,
            'capacity' => 4,
            'reserved_unit_id' => $etatMajor->id,
        ]));

        $registration = $this->confirmedRegistration($tenant, $event, ['unit_id' => $qodesh->id]);

        $assignment = $tenant->asCurrent(fn () => app(AssignTable::class)->handle($registration));

        $this->assertNull($assignment);
    }

    public function test_regroupe_par_unite_quand_aucune_table_n_est_reservee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['tables' => [2, 4]]);
        $qodesh = $this->unitNamed($tenant, 'QODESH');

        $first = $this->confirmedRegistration($tenant, $event, ['unit_id' => $qodesh->id]);
        $tenant->asCurrent(fn () => app(AssignTable::class)->handle($first));

        // Deuxieme inscription de la meme unite : doit rejoindre la table de la premiere,
        // plutot que la table 2, meme encore entierement libre.
        $second = $this->confirmedRegistration($tenant, $event, ['unit_id' => $qodesh->id]);
        $assignment = $tenant->asCurrent(fn () => app(AssignTable::class)->handle($second));

        $firstTableId = $tenant->asCurrent(fn () => $first->fresh()->tableAssignment)->seating_table_id;

        $this->assertSame($firstTableId, $assignment->seating_table_id);
    }

    public function test_respecte_les_regles_de_separation_entre_unites(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['tables' => [2, 4]]);
        $qodesh = $this->unitNamed($tenant, 'QODESH');
        $chosen = $this->unitNamed($tenant, 'CHOSEN');

        $tenant->asCurrent(fn () => UnitSeparationRule::factory()->between($qodesh->id, $chosen->id)->create([
            'event_id' => $event->id,
        ]));

        $first = $this->confirmedRegistration($tenant, $event, ['unit_id' => $qodesh->id]);
        $tenant->asCurrent(fn () => app(AssignTable::class)->handle($first));

        $second = $this->confirmedRegistration($tenant, $event, ['unit_id' => $chosen->id]);
        $assignment = $tenant->asCurrent(fn () => app(AssignTable::class)->handle($second));

        $firstTableId = $tenant->asCurrent(fn () => $first->fresh()->tableAssignment)->seating_table_id;

        // Meme largement assez de place a la premiere table : la regle de separation doit
        // l'ecarter avant meme de considerer sa capacite.
        $this->assertNotSame($firstTableId, $assignment->seating_table_id);
    }

    public function test_une_inscription_reste_non_placee_si_aucune_table_n_a_assez_de_places(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['tables' => [1, 2]]);

        $registration = $this->confirmedRegistration($tenant, $event, ['party_size' => 3]);

        $assignment = $tenant->asCurrent(fn () => app(AssignTable::class)->handle($registration));

        $this->assertNull($assignment);
        $this->assertNull($tenant->asCurrent(fn () => $registration->fresh()->tableAssignment));
    }

    public function test_l_attribution_est_idempotente(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $this->confirmedRegistration($tenant, $event);

        $first = $tenant->asCurrent(fn () => app(AssignTable::class)->handle($registration));
        $second = $tenant->asCurrent(fn () => app(AssignTable::class)->handle($registration->fresh()));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $tenant->asCurrent(fn () => RegistrationTableAssignment::count()));
    }

    /**
     * SQLite etant mono-ecrivain, ce test ne fait pas courir deux vraies requetes en parallele
     * (impossible dans ce processus unique) : il verifie que la contrainte d'unicite reelle sur
     * `registration_id` protege quand meme contre une double attribution. Voir CLAUDE.md,
     * « Base de donnees » : la grille de SECURITY.md sera rejouee sur PostgreSQL avant
     * production, avec une vraie course cette fois.
     */
    public function test_la_contrainte_d_unicite_protege_une_inscription_contre_deux_attributions(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $this->confirmedRegistration($tenant, $event);

        $tenant->asCurrent(function () use ($event, $registration) {
            $tableA = SeatingTable::factory()->create(['event_id' => $event->id, 'number' => 90, 'capacity' => 4]);
            $tableB = SeatingTable::factory()->create(['event_id' => $event->id, 'number' => 91, 'capacity' => 4]);

            RegistrationTableAssignment::create([
                'registration_id' => $registration->id,
                'seating_table_id' => $tableA->id,
            ]);

            $this->expectException(QueryException::class);

            RegistrationTableAssignment::create([
                'registration_id' => $registration->id,
                'seating_table_id' => $tableB->id,
            ]);
        });
    }
}
