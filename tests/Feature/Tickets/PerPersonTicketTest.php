<?php

namespace Tests\Feature\Tickets;

use App\Actions\Scan\ScanTicket;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\LegalForm;
use App\Enums\ScanResult;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Un billet par personne (README 2.8, decision du 2026-09-27) : l'invite et chacun de ses
 * accompagnateurs ont un billet nominatif et leur propre QR, et entrent quand ils arrivent.
 */
class PerPersonTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'registration_number' => 'CI-ABJ-2021-B-04812',
            'tax_number' => '1804517 K',
            'address' => 'Rue des Jardins',
            'city' => 'Abidjan',
            'country' => 'CI',
            'phone' => '+225 07 07 12 34 56',
        ])->save();
        $tenant->update(['subdomain' => 'convive-ci']);
        $this->tenant = $tenant->fresh();
    }

    /**
     * @return array{event: Event, registration: Registration, tickets: array<int, Ticket>}
     */
    private function confirmedGroup(): array
    {
        return $this->tenant->asCurrent(function () {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create();
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $event->id,
                'name' => 'Aya Kouassi',
                'party_size' => 3,
            ]);
            $unit = Unit::where('name', 'QODESH')->value('id');
            $registration->companions()->create(['name' => 'Kofi Kouassi', 'unit_id' => $unit, 'position' => 0]);
            $registration->companions()->create(['name' => 'Marie Kouassi', 'unit_id' => $unit, 'position' => 1]);

            app(IssueTicket::class)->handle($registration);

            return [
                'event' => $event->fresh(),
                'registration' => $registration,
                'tickets' => Ticket::where('registration_id', $registration->id)->orderBy('holder_position')->get()->all(),
            ];
        });
    }

    private function scan(Event $event, Ticket $ticket): array
    {
        return $this->tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $ticket->signedToken(), $this->owner));
    }

    public function test_un_billet_nominatif_est_emis_pour_l_invite_et_chaque_accompagnateur(): void
    {
        ['tickets' => $tickets] = $this->confirmedGroup();

        $this->assertCount(3, $tickets);
        $this->assertSame([0, 1, 2], array_map(fn (Ticket $ticket) => $ticket->holder_position, $tickets));
        $this->assertSame('Kofi Kouassi', $tickets[1]->holder_name);
        $this->assertSame('Marie Kouassi', $tickets[2]->holder_name);
        $this->assertCount(3, array_unique(array_map(fn (Ticket $ticket) => $ticket->nonce, $tickets)));
    }

    public function test_l_emission_reste_idempotente_avec_les_accompagnateurs(): void
    {
        ['registration' => $registration] = $this->confirmedGroup();

        $this->tenant->asCurrent(fn () => app(IssueTicket::class)->handle($registration));

        $this->assertSame(3, $this->tenant->asCurrent(fn () => Ticket::where('registration_id', $registration->id)->count()));
    }

    public function test_un_accompagnateur_arrive_seul_et_entre_avec_son_propre_billet(): void
    {
        ['event' => $event, 'tickets' => $tickets] = $this->confirmedGroup();

        $companion = $this->scan($event, $tickets[1]);

        $this->assertSame(ScanResult::Accepted, $companion['result']);
        $this->assertSame('Kofi Kouassi', $companion['registration']['name']);
        $this->assertSame('QODESH', $companion['registration']['unit']);
        $this->assertSame('Aya Kouassi', $companion['registration']['guestOf']);

        // L'invite arrive plus tard : son billet est intact.
        $guest = $this->scan($event, $tickets[0]);
        $this->assertSame(ScanResult::Accepted, $guest['result']);
        $this->assertNull($guest['registration']['guestOf']);
    }

    public function test_chaque_billet_ne_sert_qu_une_fois(): void
    {
        ['event' => $event, 'tickets' => $tickets] = $this->confirmedGroup();

        $this->scan($event, $tickets[2]);

        $this->assertSame(ScanResult::AlreadyScanned, $this->scan($event, $tickets[2])['result']);
    }

    public function test_l_ecran_de_scan_attend_une_personne_par_billet(): void
    {
        ['event' => $event] = $this->confirmedGroup();

        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $event]))
            ->assertInertia(fn (Assert $page) => $page->where('expectedCount', 3));
    }

    public function test_la_base_d_inscrits_compte_les_personnes_entrees(): void
    {
        ['event' => $event, 'tickets' => $tickets] = $this->confirmedGroup();

        $this->scan($event, $tickets[0]);
        $this->scan($event, $tickets[1]);

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $event]))
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.enteredCount', 2));
    }

    public function test_le_lien_individuel_d_un_billet_montre_ce_seul_billet(): void
    {
        ['tickets' => $tickets] = $this->confirmedGroup();

        $url = $this->tenant->asCurrent(fn () => $tickets[1]->fresh()->shareUrl());

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/ticket-show')
                ->where('ticket.name', 'Kofi Kouassi')
                // La personne qui l'invite, pour qu'il sache a qui se rattacher, et l'agent aussi.
                ->where('ticket.host.name', 'Aya Kouassi')
                ->has('ticket.host.unit')
                ->has('ticket.host.reference')
                ->has('ticket.qrImage'));
    }

    public function test_la_page_de_l_invite_donne_le_meme_billet_a_chaque_personne_du_groupe(): void
    {
        ['registration' => $registration] = $this->confirmedGroup();

        $url = $this->tenant->asCurrent(fn () => $registration->fresh()->signedResumeUrl());

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Billet de l'invite principal : son groupe entier, ses accompagnateurs.
                ->where('registration.ticket.card.holder.name', 'Aya Kouassi')
                ->where('registration.ticket.card.seats', 3)
                ->where('registration.ticket.card.companions.0.name', 'Kofi Kouassi')
                ->where('registration.ticket.card.host', null)
                // Billet d'un accompagnateur : meme forme, sa seule place et la personne qui l'invite.
                ->where('registration.ticket.passes.0.card.holder.name', 'Kofi Kouassi')
                ->where('registration.ticket.passes.0.card.seats', 1)
                ->where('registration.ticket.passes.0.card.companions', [])
                ->where('registration.ticket.passes.0.card.host.name', 'Aya Kouassi')
                ->has('registration.ticket.event.name'),
            );
    }

    public function test_le_lien_individuel_porte_le_meme_billet_et_le_gabarit_de_l_organisation(): void
    {
        ['tickets' => $tickets] = $this->confirmedGroup();

        $url = $this->tenant->asCurrent(fn () => $tickets[1]->fresh()->shareUrl());

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('card.holder.name', 'Kofi Kouassi')
                ->where('card.host.name', 'Aya Kouassi')
                ->has('design.model')
                ->has('design.elements')
                ->where('design.brand.displayName', 'Convive'),
            );
    }

    public function test_un_lien_individuel_altere_repond_404(): void
    {
        ['tickets' => $tickets] = $this->confirmedGroup();

        $url = $this->tenant->asCurrent(fn () => $tickets[1]->fresh()->shareUrl());

        $this->get(preg_replace('/signature=[^&]+/', 'signature=invalide', $url))->assertNotFound();
    }
}
