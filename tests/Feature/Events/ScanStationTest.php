<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ScanEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ecran de scan aligne sur le prototype (Convive.dc.html) : le poste de controle (« Entree
 * principale ») est consigne avec chaque passage, et l'ecran montre les entrees sur les attendus.
 */
class ScanStationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        [$this->event, $this->token] = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]);
            // Une inscription confirmee a toujours ses billets : l'ecran compte les personnes
            // attendues par billet emis (README 2.8, un billet par personne), pas par inscription.
            $other = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]);
            app(IssueTicket::class)->handle($other);
            Registration::factory()->held()->create(['event_id' => $event->id]);

            return [$event, app(IssueTicket::class)->handle($registration)->signedToken()];
        });
    }

    public function test_le_poste_de_controle_est_consigne_avec_le_passage(): void
    {
        $this->actingAs($this->owner)
            ->followingRedirects()
            ->post(route('tenants.events.scan.verify', [$this->tenant, $this->event]), [
                'token' => $this->token,
                'station' => 'Entrée principale',
            ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('recent.0.station', 'Entrée principale'));

        $this->assertSame('Entrée principale', $this->tenant->asCurrent(fn () => ScanEvent::firstOrFail()->station));
    }

    public function test_un_poste_trop_long_est_refuse(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.scan.verify', [$this->tenant, $this->event]), [
                'token' => $this->token,
                'station' => str_repeat('x', 61),
            ])
            ->assertSessionHasErrors('station');
    }

    public function test_l_ecran_donne_les_entrees_sur_les_inscriptions_attendues(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.scan.verify', [$this->tenant, $this->event]), ['token' => $this->token]);

        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $this->event]))
            ->assertInertia(fn ($page) => $page
                ->where('acceptedCount', 1)
                ->where('expectedCount', 2));
    }
}
