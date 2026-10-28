<?php

namespace Tests\Feature\Scan;

use App\Actions\Scan\ScanTicket;
use App\Actions\Seating\AssignTable;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Models\Event;
use App\Models\Registration;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Derniers passages » de l'ecran de scan : de quoi reconnaitre un passage d'un coup d'oeil (heure, unite,
 * table, agent), sans jamais afficher la reference du dossier ni le telephone de l'invite (decision du
 * 2026-10-28).
 */
class RecentScansTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create(['name' => 'Awa Agent']);
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    /**
     * @return array{ticket: Ticket, token: string}
     */
    private function ticket(): array
    {
        return $this->tenant->asCurrent(function () {
            $unit = Unit::where('name', 'ELIAKIM')->firstOrFail();
            SeatingTable::factory()->create(['event_id' => $this->event->id, 'capacity' => 4]);
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $this->event->id,
                'party_size' => 1,
                'unit_id' => $unit->id,
                'name' => 'Kone Mariam',
                'phone' => '+2250707123456',
                'reference' => 'CO-2026-0042',
            ]);
            app(AssignTable::class)->handle($registration);

            $ticket = app(IssueTicket::class)->handle($registration);

            return ['ticket' => $ticket, 'token' => $ticket->signedToken()];
        });
    }

    private function scan(string $token, bool $force = false): void
    {
        $this->tenant->asCurrent(fn () => app(ScanTicket::class)->handle($this->event->fresh(), $token, $this->owner, force: $force));
    }

    public function test_un_passage_montre_l_heure_l_unite_la_table_et_l_agent(): void
    {
        ['token' => $token] = $this->ticket();
        $this->scan($token);

        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $this->event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('recent', 1)
                ->where('recent.0.result', 'accepted')
                ->where('recent.0.name', 'Kone Mariam')
                ->where('recent.0.unit', 'ELIAKIM')
                ->where('recent.0.tableNumber', 1)
                ->where('recent.0.agent', 'Awa Agent')
                ->where('recent.0.forced', false)
                ->whereNot('recent.0.scannedAt', null),
            );
    }

    public function test_un_passage_ne_montre_ni_la_reference_du_dossier_ni_le_telephone(): void
    {
        ['token' => $token] = $this->ticket();
        $this->scan($token);

        $content = (string) $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $this->event]))
            ->getContent();

        $this->assertStringNotContainsString('CO-2026-0042', $content);
        $this->assertStringNotContainsString('+2250707123456', $content);
    }

    public function test_un_forcage_est_marque_dans_la_liste(): void
    {
        ['token' => $token] = $this->ticket();
        $this->scan($token);
        $this->scan($token, force: true);

        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $this->event]))
            ->assertInertia(fn ($page) => $page
                ->has('recent', 2)
                ->where('recent.0.forced', true)
                ->where('recent.0.result', 'already_scanned'),
            );
    }

    public function test_un_jeton_falsifie_s_affiche_sans_nom_ni_detail(): void
    {
        $this->scan('jeton-falsifie');

        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $this->event]))
            ->assertInertia(fn ($page) => $page
                ->has('recent', 1)
                ->where('recent.0.name', null)
                ->where('recent.0.unit', null)
                ->where('recent.0.tableNumber', null),
            );
    }
}
