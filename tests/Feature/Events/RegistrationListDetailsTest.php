<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\PaymentChannel;
use App\Enums\ProofRejectionReason;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventPriceCategory;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\TicketArrival;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Base d'inscrits alignee sur le prototype (Convive.dc.html) : telephone, canal de la preuve et
 * passage a l'entree sur chaque ligne, et un bloc des annulations avec motif, auteur et date.
 */
class RegistrationListDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create(['name' => 'Amara Kone']);
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    public function test_chaque_ligne_porte_le_telephone_le_canal_et_le_passage_a_l_entree(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $this->event->id,
                'name' => 'Kouadio Jessica',
                'phone' => '+225 07 07 12 34 56',
            ]);
            PaymentProof::factory()->create([
                'registration_id' => $registration->id,
                'payment_account_id' => PaymentAccount::factory()->create()->id,
                'channel' => PaymentChannel::Wave,
            ]);
            $ticket = app(IssueTicket::class)->handle($registration);
            TicketArrival::create(['ticket_id' => $ticket->id, 'performed_by_user_id' => $this->owner->id]);
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.phone', '+225 07 07 12 34 56')
                ->where('rows.0.channelLabel', PaymentChannel::Wave->label())
                ->where('rows.0.enteredAt', fn ($value) => is_string($value)));
    }

    public function test_chaque_ligne_detaille_les_personnes_du_dossier_avec_leur_tarif_et_leur_entree(): void
    {
        $this->tenant->asCurrent(function () {
            $paid = EventPriceCategory::factory()->create(['event_id' => $this->event->id, 'name' => 'VIP', 'price' => 15000]);
            $free = EventPriceCategory::factory()->create(['event_id' => $this->event->id, 'name' => 'Enfant', 'price' => 0]);
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $this->event->id,
                'name' => 'Aya Kouassi',
                'party_size' => 2,
                'price_category_id' => $paid->id,
            ]);
            $registration->companions()->create([
                'name' => 'Kofi Kouassi',
                'unit_id' => Unit::query()->value('id'),
                'position' => 0,
                'price_category_id' => $free->id,
            ]);
            $guestTicket = app(IssueTicket::class)->handle($registration);
            TicketArrival::create(['ticket_id' => $guestTicket->id, 'performed_by_user_id' => $this->owner->id]);
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.0.people', 2)
                ->where('rows.0.people.0.name', 'Aya Kouassi')
                ->where('rows.0.people.0.isGuest', true)
                ->where('rows.0.people.0.priceCategory.name', 'VIP')
                ->where('rows.0.people.0.priceCategory.price', 15000)
                ->where('rows.0.people.0.entered', true)
                ->where('rows.0.people.1.name', 'Kofi Kouassi')
                ->where('rows.0.people.1.isGuest', false)
                ->where('rows.0.people.1.priceCategory.name', 'Enfant')
                ->where('rows.0.people.1.priceCategory.price', 0)
                ->where('rows.0.people.1.entered', false));
    }

    public function test_avant_la_validation_le_detail_n_a_que_l_invite_et_aucune_entree(): void
    {
        $this->tenant->asCurrent(fn () => Registration::factory()->held()->create(['event_id' => $this->event->id]));

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.0.people', 1)
                ->where('rows.0.people.0.isGuest', true)
                ->where('rows.0.people.0.entered', null));
    }

    public function test_la_recherche_trouve_une_inscription_par_la_reference_de_sa_transaction(): void
    {
        $this->tenant->asCurrent(function () {
            $account = PaymentAccount::factory()->create();

            foreach (['Aya Kouame' => 'TX-ABC-123', 'Koffi Brou' => 'TX-ZZZ-999'] as $name => $reference) {
                $registration = Registration::factory()->confirmed()->create(['event_id' => $this->event->id, 'name' => $name]);
                PaymentProof::factory()->create([
                    'registration_id' => $registration->id,
                    'payment_account_id' => $account->id,
                    'reference' => $reference,
                ]);
            }
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]).'?filter[search]=abc-123')
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 1)
                ->where('rows.0.name', 'Aya Kouame')
                ->where('rows.0.proofReference', 'TX-ABC-123'));
    }

    public function test_le_motif_du_rejet_figure_sur_la_ligne_du_dossier(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->create([
                'event_id' => $this->event->id,
                'status' => RegistrationStatus::ProofRejected,
            ]);
            PaymentProof::factory()->create([
                'registration_id' => $registration->id,
                'rejection_reason' => ProofRejectionReason::EditedCapture,
                'rejection_note' => null,
            ]);
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.rejection.reason', ProofRejectionReason::EditedCapture->label())
                ->where('rows.0.rejection.note', null));
    }

    private function mixedMoney(): void
    {
        $this->tenant->asCurrent(function () {
            Registration::factory()->confirmed()->create(['event_id' => $this->event->id, 'amount_due' => 10000, 'name' => 'Aya Kouame']);
            Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id, 'amount_due' => 5000, 'name' => 'Koffi Brou']);
            Registration::factory()->proofRejected()->create(['event_id' => $this->event->id, 'amount_due' => 3000, 'name' => 'Marie Yao']);
            Registration::factory()->refundDue()->create(['event_id' => $this->event->id, 'amount_due' => 4000, 'name' => 'Paul Koffi']);
        });
    }

    public function test_les_totaux_distinguent_l_encaisse_l_en_attente_et_le_montant_du(): void
    {
        $this->mixedMoney();

        $expected = $this->tenant->asCurrent(fn () => $this->event->fresh()->paymentTotals());

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                // Le meme chiffre que le rapport de l'evenement, sans filtre.
                ->where('totals.collected', $expected['collected'])
                ->where('totals.collected', 14000)
                ->where('totals.pending', 5000)
                ->where('totals.due', 22000));
    }

    public function test_les_totaux_suivent_le_filtre_de_statut(): void
    {
        $this->mixedMoney();

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]).'?filter[status]=confirmed')
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.collected', 10000)
                ->where('totals.pending', 0)
                ->where('totals.due', 10000));
    }

    public function test_les_totaux_suivent_la_recherche(): void
    {
        $this->mixedMoney();

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]).'?filter[search]=koffi')
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.pending', 5000)
                ->where('totals.collected', 4000)
                ->where('totals.due', 9000));
    }

    public function test_les_totaux_couvrent_toutes_les_pages_pas_seulement_celle_qui_s_affiche(): void
    {
        $this->tenant->asCurrent(fn () => Registration::factory()->confirmed()->count(30)->create(['event_id' => $this->event->id, 'amount_due' => 1000]));

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 25)
                ->where('totals.collected', 30000));
    }

    public function test_sans_dossier_les_totaux_sont_a_zero(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.collected', 0)
                ->where('totals.pending', 0)
                ->where('totals.due', 0));
    }

    public function test_une_inscription_sans_preuve_ni_passage_n_a_ni_canal_ni_entree(): void
    {
        $this->tenant->asCurrent(fn () => Registration::factory()->held()->create(['event_id' => $this->event->id]));

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.channelLabel', null)
                ->where('rows.0.enteredAt', null));
    }

    public function test_le_bloc_des_annulations_donne_le_motif_l_auteur_et_la_date(): void
    {
        $this->tenant->asCurrent(fn () => Registration::factory()->cancelled()->create([
            'event_id' => $this->event->id,
            'name' => 'Bamba Esther',
            'cancellation_reason' => 'Désistement',
            'cancelled_by_user_id' => $this->owner->id,
        ]));

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('cancellations', 1)
                ->where('cancellations.0.name', 'Bamba Esther')
                ->where('cancellations.0.reason', 'Désistement')
                ->where('cancellations.0.cancelledBy', 'Amara Kone')
                ->where('cancellations.0.cancelledAt', fn ($value) => is_string($value)));
    }
}
