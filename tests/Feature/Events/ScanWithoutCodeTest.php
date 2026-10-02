<?php

namespace Tests\Feature\Events;

use App\Actions\Events\SaveEvent;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\NotificationType;
use App\Enums\ScanResult;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Profile;
use App\Models\Registration;
use App\Models\ScanEvent;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketArrival;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Entree sans scan (README ecran 26) : quand le QR ne peut pas etre lu (ecran casse, telephone
 * eteint, billet oublie), l'agent retrouve l'invite par la reference de son dossier ou par son nom,
 * puis valide son entree. Le passage est journalise comme tel, au nom de l'agent.
 */
class ScanWithoutCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        [$this->event, $this->ticket] = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();

            return [$event, $this->issueFor($event, 'Aya Kouassi', 'SP-2026-0008')];
        });
    }

    /**
     * A appeler dans le contexte de l'organisation.
     *
     * @param  array<int, string>  $companions
     */
    private function issueFor(Event $event, string $name, string $reference, array $companions = []): Ticket
    {
        $registration = Registration::factory()
            ->confirmed()
            ->create(['event_id' => $event->id, 'name' => $name, 'reference' => $reference]);

        foreach ($companions as $index => $companion) {
            $registration->companions()->create([
                'name' => $companion,
                'unit_id' => $registration->unit_id,
                'position' => $index + 1,
            ]);
        }

        return app(IssueTicket::class)->handle($registration);
    }

    private function agentWith(TenantPermission ...$permissions): User
    {
        $agent = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $agent, array_values($permissions), 'Agent '.$agent->id);

        return $agent;
    }

    /**
     * @return TestResponse<Response>
     */
    private function find(User $user, string $search, ?Event $event = null): TestResponse
    {
        return $this->actingAs($user)->get(route('tenants.events.scan.find', [
            $this->tenant, $event ?? $this->event, 'search' => $search,
        ]));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return TestResponse<Response>
     */
    private function admit(User $user, Ticket $ticket, array $extra = [], ?Event $event = null): TestResponse
    {
        return $this->actingAs($user)->post(
            route('tenants.events.scan.admit', [$this->tenant, $event ?? $this->event]),
            ['ticket' => $ticket->id, ...$extra],
        );
    }

    public function test_retrouve_un_invite_par_la_reference_de_son_dossier(): void
    {
        $this->find($this->owner, 'sp-2026-0008')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/scan')
                ->where('lookup.search', 'sp-2026-0008')
                ->has('lookup.tickets', 1)
                ->where('lookup.tickets.0.id', $this->ticket->id)
                ->where('lookup.tickets.0.name', 'Aya Kouassi')
                ->where('lookup.tickets.0.reference', 'SP-2026-0008')
                ->where('lookup.tickets.0.arrivedAt', null),
            );
    }

    public function test_retrouve_un_invite_par_une_partie_de_son_nom(): void
    {
        $this->find($this->owner, 'kouas')
            ->assertInertia(fn (Assert $page) => $page
                ->has('lookup.tickets', 1)
                ->where('lookup.tickets.0.name', 'Aya Kouassi'),
            );
    }

    public function test_la_reference_d_un_dossier_rend_un_billet_par_personne_du_groupe(): void
    {
        $this->tenant->asCurrent(fn () => $this->issueFor($this->event, 'Kofi Diallo', 'SP-2026-0009', ['Mariam Diallo', 'Serge Bamba']));

        $this->find($this->owner, 'SP-2026-0009')
            ->assertInertia(fn (Assert $page) => $page
                ->has('lookup.tickets', 3)
                ->where('lookup.tickets.0.name', 'Kofi Diallo')
                ->where('lookup.tickets.0.guestOf', null)
                ->where('lookup.tickets.1.name', 'Mariam Diallo')
                ->where('lookup.tickets.1.guestOf', 'Kofi Diallo')
                ->where('lookup.tickets.2.name', 'Serge Bamba')
                ->where('lookup.tickets.2.guestOf', 'Kofi Diallo'),
            );
    }

    public function test_retrouve_un_accompagnateur_par_son_propre_nom(): void
    {
        $this->tenant->asCurrent(fn () => $this->issueFor($this->event, 'Kofi Diallo', 'SP-2026-0009', ['Serge Bamba']));

        $this->find($this->owner, 'bamba')
            ->assertInertia(fn (Assert $page) => $page
                ->has('lookup.tickets', 1)
                ->where('lookup.tickets.0.name', 'Serge Bamba')
                ->where('lookup.tickets.0.guestOf', 'Kofi Diallo'),
            );
    }

    public function test_une_recherche_de_moins_de_trois_caracteres_ne_rend_personne(): void
    {
        // Sinon « a » donnerait la liste des invites a qui tient le telephone.
        $this->find($this->owner, 'ay')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lookup.tooShort', true)
                ->has('lookup.tickets', 0),
            );
    }

    public function test_la_recherche_ne_rend_que_les_inscriptions_confirmees(): void
    {
        $this->tenant->asCurrent(fn () => Registration::factory()->held()->create([
            'event_id' => $this->event->id,
            'name' => 'Aya Kone',
        ]));

        $this->find($this->owner, 'aya')
            ->assertInertia(fn (Assert $page) => $page
                ->has('lookup.tickets', 1)
                ->where('lookup.tickets.0.name', 'Aya Kouassi'),
            );
    }

    public function test_la_recherche_reste_dans_l_evenement_controle(): void
    {
        $other = $this->tenant->asCurrent(function () {
            $other = Event::factory()->open()->create();
            $this->issueFor($other, 'Aya Kouassi', 'SP-2026-0010');

            return $other;
        });

        $this->find($this->owner, 'SP-2026-0008', $other)
            ->assertInertia(fn (Assert $page) => $page->has('lookup.tickets', 0));
    }

    public function test_la_recherche_est_limitee_et_le_dit(): void
    {
        $this->tenant->asCurrent(function () {
            foreach (range(1, 12) as $index) {
                $this->issueFor($this->event, "Kouassi Invite {$index}", sprintf('SP-2026-01%02d', $index));
            }
        });

        $this->find($this->owner, 'kouassi')
            ->assertInertia(fn (Assert $page) => $page
                ->has('lookup.tickets', 10)
                ->where('lookup.truncated', true),
            );
    }

    public function test_la_recherche_montre_qu_un_invite_est_deja_entre(): void
    {
        $this->admit($this->owner, $this->ticket);

        $this->find($this->owner, 'SP-2026-0008')
            ->assertInertia(fn (Assert $page) => $page
                ->where('lookup.tickets.0.arrivedAt', fn ($value) => is_string($value))
                ->where('lookup.tickets.0.arrivedBy', $this->owner->name),
            );
    }

    public function test_l_ecran_de_scan_n_ouvre_aucune_recherche_de_lui_meme(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page->where('lookup', null));
    }

    public function test_rechercher_un_invite_exige_la_permission_dediee(): void
    {
        // Savoir scanner ne suffit pas : le QR prouve que l'invite detient son billet, un nom non.
        $this->find($this->agentWith(TenantPermission::ScanPerform), 'kouassi')->assertForbidden();

        $this->find($this->agentWith(TenantPermission::ScanPerform, TenantPermission::ScanManual), 'kouassi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('lookup.tickets', 1));
    }

    public function test_la_permission_d_entree_sans_scan_ne_remplace_pas_celle_de_scanner(): void
    {
        $agent = $this->agentWith(TenantPermission::EventsView, TenantPermission::ScanManual);

        $this->find($agent, 'kouassi')->assertForbidden();
        $this->admit($agent, $this->ticket)->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_la_recherche_et_sur_la_validation(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();

        $this->find($stranger, 'kouassi')->assertNotFound();
        $this->admit($stranger, $this->ticket)->assertNotFound();
    }

    public function test_valide_l_entree_d_un_invite_sans_scanner_son_billet(): void
    {
        $this->admit($this->owner, $this->ticket, ['station' => 'Entree principale'])
            ->assertRedirect(route('tenants.events.scan.index', [$this->tenant, $this->event]));

        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $this->event]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.result', 'accepted')
                ->where('result.manual', true)
                ->where('result.registration.name', 'Aya Kouassi')
                ->where('acceptedCount', 1)
                ->where('recent.0.manual', true),
            );

        $this->tenant->asCurrent(function () {
            $this->assertSame(1, TicketArrival::where('ticket_id', $this->ticket->id)->count());

            $scan = ScanEvent::sole();

            $this->assertSame(ScanResult::Accepted, $scan->result);
            $this->assertTrue($scan->manual);
            $this->assertFalse($scan->forced);
            $this->assertSame($this->owner->id, $scan->performed_by_user_id);
            $this->assertSame('Entree principale', $scan->station);
        });
    }

    public function test_un_billet_scanne_reste_journalise_comme_scanne(): void
    {
        $token = $this->tenant->asCurrent(fn () => $this->ticket->signedToken());

        $this->actingAs($this->owner)
            ->followingRedirects()
            ->post(route('tenants.events.scan.verify', [$this->tenant, $this->event]), ['token' => $token])
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.result', 'accepted')
                ->where('result.manual', false)
                ->where('recent.0.manual', false),
            );

        $this->assertFalse($this->tenant->asCurrent(fn () => ScanEvent::sole()->manual));
    }

    public function test_valider_sans_scan_exige_la_permission_dediee(): void
    {
        $this->admit($this->agentWith(TenantPermission::ScanPerform), $this->ticket)->assertForbidden();

        $this->assertSame(0, $this->tenant->asCurrent(fn () => TicketArrival::count()));
    }

    public function test_un_invite_deja_entre_n_entre_pas_une_seconde_fois_sans_scan(): void
    {
        $this->admit($this->owner, $this->ticket);

        $this->actingAs($this->owner)
            ->followingRedirects()
            ->post(route('tenants.events.scan.admit', [$this->tenant, $this->event]), ['ticket' => $this->ticket->id])
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.result', 'already_scanned')
                ->where('result.forced', false)
                ->where('result.firstScannedBy', $this->owner->name)
                ->where('acceptedCount', 1),
            );

        $this->assertSame(1, $this->tenant->asCurrent(fn () => TicketArrival::count()));
    }

    public function test_forcer_une_entree_sans_scan_exige_la_permission_de_forcer(): void
    {
        $this->admit($this->owner, $this->ticket);

        $agent = $this->agentWith(TenantPermission::ScanPerform, TenantPermission::ScanManual);
        $this->admit($agent, $this->ticket, ['force' => true])->assertForbidden();

        $this->admit($this->owner, $this->ticket, ['force' => true])->assertRedirect();

        $forced = $this->tenant->asCurrent(fn () => ScanEvent::where('forced', true)->sole());

        $this->assertTrue($forced->manual);
        $this->assertSame(ScanResult::AlreadyScanned, $forced->result);
    }

    public function test_le_billet_d_un_autre_evenement_n_est_pas_valide_ici(): void
    {
        $other = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());

        $this->admit($this->owner, $this->ticket, event: $other)->assertNotFound();

        $this->tenant->asCurrent(function () {
            $this->assertSame(0, TicketArrival::count());
            $this->assertSame(0, ScanEvent::count());
        });
    }

    public function test_un_billet_inconnu_recoit_404(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.scan.admit', [$this->tenant, $this->event]), ['ticket' => 999999])
            ->assertNotFound();
    }

    public function test_une_inscription_annulee_entre_temps_est_refusee_sans_alerter_comme_une_fraude(): void
    {
        Notification::fake();
        $watcher = $this->agentWith(TenantPermission::ScanLogView);

        $this->tenant->asCurrent(fn () => $this->ticket->registration->forceFill(['status' => 'cancelled'])->save());

        $this->actingAs($this->owner)
            ->followingRedirects()
            ->post(route('tenants.events.scan.admit', [$this->tenant, $this->event]), ['ticket' => $this->ticket->id])
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.result', 'refused')
                ->where('result.registration', null),
            );

        $this->assertSame(0, $this->tenant->asCurrent(fn () => TicketArrival::count()));
        Notification::assertNotSentTo($watcher, TenantAlert::class);
    }

    public function test_un_evenement_clos_ne_laisse_plus_entrer_sans_scan(): void
    {
        $this->tenant->asCurrent(fn () => app(SaveEvent::class)->close($this->event));

        $this->actingAs($this->owner)
            ->followingRedirects()
            ->post(route('tenants.events.scan.admit', [$this->tenant, $this->event]), ['ticket' => $this->ticket->id])
            ->assertInertia(fn (Assert $page) => $page->where('result.result', 'refused'));

        $this->assertSame(0, $this->tenant->asCurrent(fn () => TicketArrival::count()));
    }

    public function test_la_recherche_et_la_validation_partagent_la_limite_de_debit_du_scan(): void
    {
        foreach (range(1, 60) as $attempt) {
            $this->find($this->owner, 'kouassi')->assertOk();
        }

        $this->find($this->owner, 'kouassi')->assertTooManyRequests();
        $this->admit($this->owner, $this->ticket)->assertTooManyRequests();
    }

    public function test_seul_le_proprietaire_recoit_la_permission_a_l_ouverture_d_une_organisation(): void
    {
        // Faire entrer quelqu'un sur son seul nom est une decision de responsable : a lui de la
        // confier a qui il veut depuis l'ecran des profils.
        $holders = $this->tenant->asCurrent(fn () => Profile::query()
            ->whereHas('permissions', fn ($query) => $query->where('name', TenantPermission::ScanManual->value))
            ->pluck('name')
            ->all());

        $this->assertSame([Profile::Owner], $holders);
    }

    public function test_retrouve_un_invite_au_nom_accentue_par_une_saisie_sans_accent(): void
    {
        $this->tenant->asCurrent(fn () => $this->issueFor($this->event, 'Yao Kouamé', 'SP-2026-0011'));

        $this->find($this->owner, 'kouame')
            ->assertInertia(fn (Assert $page) => $page
                ->has('lookup.tickets', 1)
                ->where('lookup.tickets.0.name', 'Yao Kouamé'),
            );
    }

    public function test_une_entree_sans_scan_previent_ceux_qui_suivent_l_historique(): void
    {
        Notification::fake();
        $manager = $this->agentWith(TenantPermission::AuditView);
        $hostess = $this->agentWith(TenantPermission::ScanPerform, TenantPermission::ScanLogView);

        $this->admit($this->owner, $this->ticket);

        Notification::assertSentTo($manager, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::EntryWithoutScan
            && $alert->params['guest'] === 'Aya Kouassi'
            && $alert->params['agent'] === $this->owner->name);
        Notification::assertNotSentTo($hostess, TenantAlert::class);
        // L'agent n'a pas besoin d'etre prevenu de ce qu'il vient de faire.
        Notification::assertNotSentTo($this->owner, TenantAlert::class);
    }

    public function test_une_entree_forcee_sans_scan_previent_aussi(): void
    {
        $this->admit($this->owner, $this->ticket);

        Notification::fake();
        $manager = $this->agentWith(TenantPermission::AuditView);

        // Deja entre, non force : personne n'est entre, rien a signaler.
        $this->admit($this->owner, $this->ticket);
        Notification::assertNothingSent();

        $this->admit($this->owner, $this->ticket, ['force' => true]);
        Notification::assertSentTo($manager, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::EntryWithoutScan);
    }

    public function test_un_billet_scanne_ne_previent_personne(): void
    {
        Notification::fake();
        $this->agentWith(TenantPermission::AuditView);

        $token = $this->tenant->asCurrent(fn () => $this->ticket->signedToken());

        $this->actingAs($this->owner)
            ->post(route('tenants.events.scan.verify', [$this->tenant, $this->event]), ['token' => $token]);

        Notification::assertNothingSent();
    }
}
