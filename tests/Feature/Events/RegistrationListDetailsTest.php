<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\PaymentChannel;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\TicketArrival;
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
