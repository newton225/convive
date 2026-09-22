<?php

namespace Tests\Feature\Registrations;

use App\Actions\Registrations\CancelRegistration;
use App\Actions\Scan\ScanTicket;
use App\Actions\Seating\MoveRegistrationToTable;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\RegistrationStatus;
use App\Enums\ScanResult;
use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CancelRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    public function test_annule_une_inscription_confirmee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Doublon avec une autre inscription.', $owner));

        $tenant->asCurrent(function () use ($registration) {
            $fresh = $registration->fresh();
            $this->assertSame(RegistrationStatus::Cancelled, $fresh->status);
            $this->assertSame('Doublon avec une autre inscription.', $fresh->cancellation_reason);
            $this->assertNotNull($fresh->cancelled_at);
        });
    }

    public function test_l_annulation_libere_l_attribution_de_table(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]));
        $table = $tenant->asCurrent(fn () => SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]));
        $tenant->asCurrent(fn () => app(MoveRegistrationToTable::class)->handle($registration, $table, $owner));

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $tenant->asCurrent(function () use ($registration) {
            $this->assertSame(0, RegistrationTableAssignment::where('registration_id', $registration->id)->count());
        });
    }

    public function test_une_inscription_annulee_n_occupe_plus_de_place(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]));

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $tenant->asCurrent(function () use ($event) {
            $this->assertSame(0, $event->fresh()->confirmedSeats());
            $this->assertSame(0, $event->fresh()->occupiedSeats());
        });
    }

    public function test_une_inscription_annulee_n_est_jamais_purgee_automatiquement(): void
    {
        $this->assertNotContains(RegistrationStatus::Cancelled, Registration::UnfinalizedStatuses);
    }

    public function test_un_billet_rescanne_apres_annulation_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]));
        $token = $tenant->asCurrent(fn () => app(IssueTicket::class)->handle($registration)->signedToken());

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $result = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $token, $owner));

        $this->assertSame(ScanResult::Refused, $result['result']);
    }

    public function test_l_annulation_d_une_inscription_confirmee_invite_le_premier_de_la_liste_d_attente(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->create(['table_count' => 1, 'seats_per_table' => 1]));

        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]));
        $tenant->asCurrent(fn () => WaitlistEntry::factory()->create(['event_id' => $event->id]));

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif.', $owner));

        $entry = $tenant->asCurrent(fn () => WaitlistEntry::first());
        $this->assertSame(WaitlistStatus::Invited, $entry->status);
    }

    public function test_chaque_annulation_est_journalisee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Motif precis.', $owner));

        $activity = $tenant->asCurrent(
            fn () => Activity::where('description', 'registrations.cancelled')->latest('id')->first(),
        );

        $this->assertNotNull($activity);
        $this->assertSame('Motif precis.', $activity->properties['attributes']['reason']);
    }

    public function test_une_seconde_annulation_est_sans_effet(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration, 'Premier motif.', $owner));
        $tenant->asCurrent(fn () => app(CancelRegistration::class)->handle($registration->fresh(), 'Second motif.', $owner));

        $tenant->asCurrent(function () use ($registration) {
            $this->assertSame('Premier motif.', $registration->fresh()->cancellation_reason);
        });
    }
}
