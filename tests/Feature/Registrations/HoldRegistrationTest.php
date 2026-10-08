<?php

namespace Tests\Feature\Registrations;

use App\Actions\Registrations\HoldRegistration;
use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldRegistrationTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function draftOf(Tenant $tenant, Event $event, array $attributes = []): Registration
    {
        return $tenant->asCurrent(fn () => Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Draft,
            'amount_due' => 5000,
            ...$attributes,
        ]));
    }

    /**
     * SQLite etant mono-ecrivain, ce test ne fait pas courir deux vraies requetes en parallele
     * (impossible dans ce processus unique) : il verifie que la seconde tentative, executee
     * juste apres la premiere sur une capacite deja epuisee, echoue plutot que de survendre.
     * Voir CLAUDE.md, « Base de donnees » : la grille de SECURITY.md sera rejouee sur PostgreSQL
     * avant production, avec une vraie course cette fois.
     */
    public function test_deux_reservations_simultanees_sur_la_derniere_place_une_seule_aboutit(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['tables' => [1, 1]]);

        $first = $this->draftOf($tenant, $event, ['party_size' => 1]);
        $second = $this->draftOf($tenant, $event, ['party_size' => 1]);

        $firstSucceeded = $tenant->asCurrent(fn () => app(HoldRegistration::class)->handle($event, $first));
        $secondSucceeded = $tenant->asCurrent(fn () => app(HoldRegistration::class)->handle($event, $second));

        $this->assertTrue($firstSucceeded);
        $this->assertFalse($secondSucceeded);

        $tenant->asCurrent(function () use ($first, $second) {
            $this->assertSame(RegistrationStatus::Held, $first->fresh()->status);
            $this->assertSame(RegistrationStatus::Draft, $second->fresh()->status);
        });
    }

    public function test_une_reservation_ne_peut_pas_etre_tenue_si_le_groupe_depasse_les_places_restantes(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['tables' => [1, 2]]);

        $registration = $this->draftOf($tenant, $event, ['party_size' => 3]);

        $succeeded = $tenant->asCurrent(fn () => app(HoldRegistration::class)->handle($event, $registration));

        $this->assertFalse($succeeded);
        $this->assertSame(RegistrationStatus::Draft, $tenant->asCurrent(fn () => $registration->fresh())->status);
    }

    public function test_une_reservation_confirmee_occupe_une_place_meme_sans_decompte(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['tables' => [1, 1]]);

        $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'party_size' => 1,
        ]));

        // Une confirmee n'a pas de `held_until` : README 2.2 la compte quand meme, sans
        // condition de decompte, contrairement a une reservation seulement tenue.
        $this->assertSame(0, $tenant->asCurrent(fn () => $event->fresh()->remainingSeats()));
    }

    public function test_une_reservation_dont_le_decompte_est_ecoule_ne_compte_plus_dans_la_disponibilite(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant, ['tables' => [1, 1]]);

        $this->draftOf($tenant, $event, [
            'status' => RegistrationStatus::Held,
            'held_until' => now()->subMinute(),
            'party_size' => 1,
        ]);

        $this->assertSame(1, $tenant->asCurrent(fn () => $event->fresh()->remainingSeats()));
    }

    public function test_la_contrainte_d_unicite_protege_hold_sequence_meme_sans_le_verrou(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $tenant->asCurrent(function () use ($event) {
            Registration::factory()->create([
                'event_id' => $event->id,
                'status' => RegistrationStatus::Held,
                'hold_sequence' => 1,
            ]);

            $this->expectException(QueryException::class);

            // Meme jeton, meme evenement : la contrainte d'unicite reelle (voir la migration qui
            // l'ajoute) est le filet de secours quand ce n'est plus `Cache::lock()` qui
            // serialise deux ecritures. Teste ici a la base, hors de toute action, pour prouver
            // qu'elle existe independamment du verrou applicatif.
            Registration::factory()->create([
                'event_id' => $event->id,
                'status' => RegistrationStatus::Held,
                'hold_sequence' => 1,
            ]);
        });
    }
}
