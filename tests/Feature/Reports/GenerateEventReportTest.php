<?php

namespace Tests\Feature\Reports;

use App\Actions\Reports\GenerateEventReport;
use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Enums\ScanResult;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ScanEvent;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketArrival;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rapport post-evenement (README ecran 22), etape 9 de « Ordre de construction ».
 *
 * Presence et recettes se comptent sur les seules inscriptions confirmees, groupees par l'unite
 * du participant principal (un billet par dossier, voir `GenerateEventReport`).
 */
class GenerateEventReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Event $event;

    private int $agentId;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->withTwoFactor()->create();
        $this->agentId = $owner->id;
        $this->tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    private function unitId(string $name): int
    {
        return (int) Unit::query()->where('name', $name)->value('id');
    }

    /**
     * Cree une inscription confirmee avec un billet par personne (README 2.8), dont les `$arrivedCount`
     * premiers sont scannes ; `$arrived` vrai scanne tout le groupe.
     */
    private function confirmed(string $unit, int $partySize, int $amountDue, bool $arrived, ?Event $event = null, ?int $arrivedCount = null): Registration
    {
        $event ??= $this->event;

        $registration = Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'unit_id' => $this->unitId($unit),
            'party_size' => $partySize,
            'amount_due' => $amountDue,
        ]);

        $arrivedCount ??= $arrived ? $partySize : 0;

        for ($position = 0; $position < $partySize; $position++) {
            $ticket = Ticket::factory()->create(['registration_id' => $registration->id, 'holder_position' => $position]);

            if ($position < $arrivedCount) {
                // `performed_by_user_id` explicite : `User::factory()` creerait une organisation
                // personnelle sous la tenancy de ce locataire.
                TicketArrival::factory()->create(['ticket_id' => $ticket->id, 'performed_by_user_id' => $this->agentId]);
            }
        }

        return $registration;
    }

    /**
     * @return array<string, mixed>
     */
    private function report(?Event $event = null): array
    {
        return $this->tenant->asCurrent(fn () => app(GenerateEventReport::class)->handle($event ?? $this->event));
    }

    public function test_compte_les_dossiers_et_les_places_confirmes(): void
    {
        $this->tenant->asCurrent(function () {
            $this->confirmed('ELIAKIM', 3, 30000, true);
            $this->confirmed('QODESH', 1, 10000, false);
        });

        $report = $this->report();

        $this->assertSame(2, $report['confirmedRegistrations']);
        $this->assertSame(4, $report['confirmedSeats']);
    }

    public function test_les_presents_et_les_absents_se_deduisent_du_premier_passage(): void
    {
        $this->tenant->asCurrent(function () {
            $this->confirmed('ELIAKIM', 3, 30000, true);
            $this->confirmed('ELIAKIM', 2, 20000, true);
            $this->confirmed('QODESH', 1, 10000, false);
        });

        $report = $this->report();

        $this->assertSame(2, $report['presentRegistrations']);
        $this->assertSame(5, $report['presentSeats']);
        $this->assertSame(1, $report['absentRegistrations']);
        $this->assertSame(1, $report['absentSeats']);
    }

    public function test_une_arrivee_partielle_compte_les_seules_personnes_entrees(): void
    {
        // L'invite et un accompagnateur sont entres, le troisieme n'est jamais venu.
        $this->tenant->asCurrent(fn () => $this->confirmed('ELIAKIM', 3, 30000, false, arrivedCount: 2));

        $report = $this->report();

        $this->assertSame(1, $report['presentRegistrations']);
        $this->assertSame(2, $report['presentSeats']);
        $this->assertSame(0, $report['absentRegistrations']);
        $this->assertSame(1, $report['absentSeats']);
    }

    public function test_les_recettes_somment_les_montants_des_seules_inscriptions_confirmees(): void
    {
        $this->tenant->asCurrent(function () {
            $this->confirmed('ELIAKIM', 1, 10000, true);
            $this->confirmed('QODESH', 2, 20000, false);

            Registration::factory()->cancelled()->create(['event_id' => $this->event->id, 'amount_due' => 99000]);
            Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id, 'amount_due' => 55000]);
        });

        $this->assertSame(30000, $this->report()['collectedAmount']);
    }

    public function test_une_inscription_annulee_ne_compte_pas_comme_absente(): void
    {
        $this->tenant->asCurrent(function () {
            $this->confirmed('ELIAKIM', 1, 10000, true);

            $registration = $this->confirmed('QODESH', 1, 10000, false);
            $registration->update([
                'status' => RegistrationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => 'Motif.',
            ]);
        });

        $report = $this->report();

        $this->assertSame(1, $report['confirmedRegistrations']);
        $this->assertSame(0, $report['absentRegistrations']);
    }

    public function test_les_donnees_d_un_autre_evenement_ne_sont_pas_comptees(): void
    {
        $this->tenant->asCurrent(function () {
            $this->confirmed('ELIAKIM', 1, 10000, true);

            $other = Event::factory()->open()->create();
            $this->confirmed('ELIAKIM', 4, 40000, true, $other);
        });

        $report = $this->report();

        $this->assertSame(1, $report['confirmedRegistrations']);
        $this->assertSame(10000, $report['collectedAmount']);
    }

    public function test_la_ventilation_par_unite_suit_l_unite_du_participant_principal(): void
    {
        $this->tenant->asCurrent(function () {
            $this->confirmed('ELIAKIM', 3, 30000, true);
            $this->confirmed('ELIAKIM', 1, 10000, false);
            $this->confirmed('QODESH', 2, 20000, true);
        });

        $units = collect($this->report()['units'])->keyBy('unit');

        $this->assertSame(2, $units['ELIAKIM']['confirmedRegistrations']);
        $this->assertSame(1, $units['ELIAKIM']['presentRegistrations']);
        $this->assertSame(40000, $units['ELIAKIM']['collectedAmount']);
        $this->assertSame(1, $units['QODESH']['confirmedRegistrations']);
        $this->assertSame(20000, $units['QODESH']['collectedAmount']);
    }

    public function test_une_unite_sans_inscription_confirmee_n_apparait_pas_dans_la_ventilation(): void
    {
        $this->tenant->asCurrent(fn () => $this->confirmed('ELIAKIM', 1, 10000, true));

        $units = collect($this->report()['units'])->pluck('unit')->all();

        $this->assertSame(['ELIAKIM'], $units);
    }

    public function test_la_duree_moyenne_de_controle_est_l_ecart_moyen_entre_scans_acceptes_consecutifs(): void
    {
        $start = now()->startOfMinute();

        $this->tenant->asCurrent(function () use ($start) {
            // Ecarts de 10 s puis 30 s : moyenne exacte de 20 s.
            foreach ([0, 10, 40] as $offset) {
                ScanEvent::factory()->create([
                    'event_id' => $this->event->id,
                    'ticket_id' => null,
                    'performed_by_user_id' => $this->agentId,
                    'result' => ScanResult::Accepted,
                    'created_at' => $start->copy()->addSeconds($offset),
                ]);
            }

            // Un refus et un doublon entre deux scans acceptes ne changent pas le debit.
            ScanEvent::factory()->create(['event_id' => $this->event->id, 'ticket_id' => null, 'performed_by_user_id' => $this->agentId, 'result' => ScanResult::Refused, 'created_at' => $start->copy()->addSeconds(5)]);
            ScanEvent::factory()->create(['event_id' => $this->event->id, 'ticket_id' => null, 'performed_by_user_id' => $this->agentId, 'result' => ScanResult::AlreadyScanned, 'created_at' => $start->copy()->addSeconds(20)]);
        });

        $this->assertSame(20, $this->report()['averageScanIntervalSeconds']);
    }

    public function test_la_duree_moyenne_de_controle_est_nulle_sous_deux_scans_acceptes(): void
    {
        $this->tenant->asCurrent(fn () => ScanEvent::factory()->create([
            'event_id' => $this->event->id,
            'ticket_id' => null,
            'performed_by_user_id' => $this->agentId,
            'result' => ScanResult::Accepted,
        ]));

        $this->assertNull($this->report()['averageScanIntervalSeconds']);
    }

    public function test_un_evenement_sans_inscription_donne_un_rapport_a_zero(): void
    {
        $report = $this->report();

        $this->assertSame(0, $report['confirmedRegistrations']);
        $this->assertSame(0, $report['collectedAmount']);
        $this->assertSame([], $report['units']);
        $this->assertNull($report['averageScanIntervalSeconds']);
    }
}
