<?php

namespace Tests\Feature\Registrations;

use App\Actions\Registrations\PurgeRegistrations;
use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PurgeRegistrationsTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    public function test_la_purge_supprime_les_inscriptions_non_finalisees(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->create());

        $tenant->asCurrent(function () use ($event) {
            Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Draft]);
            Registration::factory()->held()->create(['event_id' => $event->id, 'party_size' => 2]);
            Registration::factory()->expired()->create(['event_id' => $event->id]);
            Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::ProofRejected]);
            Registration::factory()->confirmed()->create(['event_id' => $event->id]);
        });

        $result = $tenant->asCurrent(fn () => app(PurgeRegistrations::class)->handle($event));

        $this->assertSame(4, $result['count']);
        // Seule la reservation encore active (`Held`, decompte non ecoule) liberait une place :
        // le brouillon n'en consommait aucune, l'expiree et la preuve rejetee non plus.
        $this->assertSame(2, $result['seatsFreed']);

        $tenant->asCurrent(function () use ($event) {
            $this->assertSame(1, Registration::where('event_id', $event->id)->count());
            $this->assertSame(
                RegistrationStatus::Confirmed,
                Registration::where('event_id', $event->id)->first()->status,
            );
        });
    }

    public function test_la_purge_n_efface_rien_quand_il_n_y_a_rien_a_purger(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->create());

        $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $result = $tenant->asCurrent(fn () => app(PurgeRegistrations::class)->handle($event));

        $this->assertSame(0, $result['count']);
        $this->assertSame(0, $result['seatsFreed']);
    }

    public function test_chaque_purge_est_journalisee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->create());

        $tenant->asCurrent(fn () => Registration::factory()->held()->create(['event_id' => $event->id]));

        $tenant->asCurrent(fn () => app(PurgeRegistrations::class)->handle($event));

        $activity = $tenant->asCurrent(
            fn () => Activity::where('description', 'registrations.purged')->latest('id')->first(),
        );

        $this->assertNotNull($activity);
        $this->assertSame(1, $activity->properties['count']);
    }

    public function test_une_purge_qui_libere_une_place_invite_le_premier_de_la_liste_d_attente(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->create(['table_count' => 1, 'seats_per_table' => 1]));

        $tenant->asCurrent(function () use ($event) {
            Registration::factory()->held()->create(['event_id' => $event->id, 'party_size' => 1]);
            WaitlistEntry::factory()->create(['event_id' => $event->id]);
        });

        $tenant->asCurrent(fn () => app(PurgeRegistrations::class)->handle($event));

        $entry = $tenant->asCurrent(fn () => WaitlistEntry::first());
        $this->assertSame(WaitlistStatus::Invited, $entry->status);
    }
}
