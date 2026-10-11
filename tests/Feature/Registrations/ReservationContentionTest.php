<?php

namespace Tests\Feature\Registrations;

use App\Actions\Registrations\CreateRegistration;
use App\Actions\Registrations\HoldRegistration;
use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Exceptions\ReservationBusy;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\DeadlockException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Sous forte concurrence, SQLite refuse une ecriture (« database is locked ») plutot que de
 * l'attendre. Campagne `qa/race.mjs` du 2026-10-11 : aucune survente, mais 88 candidats sur 100
 * recevaient cette erreur au lieu d'un refus propre. Une ecriture refusee se retente ; si elle
 * echoue encore, l'invite lit un message qui dit de reessayer, jamais une page d'erreur ni un
 * faux « complet ».
 */
class ReservationContentionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Event $event;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association Convive');

        [$this->event, $this->unit] = $this->tenant->asCurrent(fn () => [
            Event::factory()->open()->create(['tables' => [2, 10]]),
            Unit::query()->first(),
        ]);
    }

    private function locked(): DeadlockException
    {
        return new DeadlockException('SQLSTATE[HY000]: General error: 5 database is locked');
    }

    private function draft(): Registration
    {
        return Registration::factory()->create([
            'event_id' => $this->event->id,
            'status' => RegistrationStatus::Draft,
            'amount_due' => 5000,
            'party_size' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function guestData(): array
    {
        return [
            'name' => 'Awa Kone',
            'phone' => '+2250707123456',
            'email' => null,
            'unit_id' => $this->unit->id,
            'companions' => [],
        ];
    }

    public function test_une_ecriture_refusee_par_la_base_est_retentee_et_la_reservation_aboutit(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = $this->draft();
            $refusals = 1;

            Registration::updating(function () use (&$refusals) {
                if ($refusals-- > 0) {
                    throw $this->locked();
                }
            });

            $this->assertTrue(app(HoldRegistration::class)->handle($this->event, $registration));
            $this->assertSame(RegistrationStatus::Held, $registration->fresh()->status);
        });
    }

    public function test_une_ecriture_toujours_refusee_leve_un_signal_d_affluence_pas_un_complet(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = $this->draft();

            $refusals = PHP_INT_MAX;

            Registration::updating(function () use (&$refusals) {
                if ($refusals > 0) {
                    throw $this->locked();
                }
            });

            try {
                app(HoldRegistration::class)->handle($this->event, $registration);
                $this->fail('Un signal d\'affluence etait attendu.');
            } catch (ReservationBusy $busy) {
                $refusals = 0;
                $this->assertInstanceOf(ReservationBusy::class, $busy);
            }
        });
    }

    public function test_une_place_non_prise_par_affluence_reste_libre(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = $this->draft();
            $seatsBefore = $this->event->remainingSeats();
            $refusals = PHP_INT_MAX;

            Registration::updating(function () use (&$refusals) {
                if ($refusals > 0) {
                    throw $this->locked();
                }
            });

            try {
                app(HoldRegistration::class)->handle($this->event, $registration);
                $this->fail('Un signal d\'affluence etait attendu.');
            } catch (ReservationBusy) {
                // attendu
            }

            $refusals = 0;
            $this->assertSame($seatsBefore, $this->event->remainingSeats());
            $this->assertSame(RegistrationStatus::Draft, $registration->fresh()->status);
        });
    }

    public function test_la_creation_du_dossier_retente_une_ecriture_refusee(): void
    {
        $this->tenant->asCurrent(function () {
            $refusals = 1;

            Registration::creating(function () use (&$refusals) {
                if ($refusals-- > 0) {
                    throw $this->locked();
                }
            });

            $created = app(CreateRegistration::class)->handle($this->event, $this->guestData());

            $this->assertSame('Awa Kone', $created['registration']->name);
        });
    }

    public function test_la_creation_du_dossier_toujours_refusee_leve_un_signal_d_affluence(): void
    {
        $this->tenant->asCurrent(function () {
            $refusals = PHP_INT_MAX;

            Registration::creating(function () use (&$refusals) {
                if ($refusals > 0) {
                    throw $this->locked();
                }
            });

            try {
                app(CreateRegistration::class)->handle($this->event, $this->guestData());
                $this->fail('Un signal d\'affluence etait attendu.');
            } catch (ReservationBusy $busy) {
                $refusals = 0;
                $this->assertInstanceOf(ReservationBusy::class, $busy);
            }
        });
    }

    public function test_le_signal_d_affluence_devient_un_message_qui_dit_de_reessayer(): void
    {
        Route::middleware('web')->post('/_test/affluence', fn () => throw new ReservationBusy);

        $this->from('/precedente')
            ->post('/_test/affluence')
            ->assertRedirect('/precedente')
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash('toast.message', __('guest.flash.reservation_busy'));
    }
}
