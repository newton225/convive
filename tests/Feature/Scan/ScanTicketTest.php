<?php

namespace Tests\Feature\Scan;

use App\Actions\Events\SaveEvent;
use App\Actions\Scan\ScanTicket;
use App\Actions\Seating\AssignTable;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\ScanResult;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ScanEvent;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification d'un billet au scan (README 2.8, ecran 26), etape 7 de « Ordre de construction ».
 */
class ScanTicketTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @return array{event: Event, ticket: Ticket, token: string}
     */
    private function confirmedTicket(Tenant $tenant, Event $event): array
    {
        return $tenant->asCurrent(function () use ($event) {
            $table = SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]);
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]);
            app(AssignTable::class)->handle($registration);

            $ticket = app(IssueTicket::class)->handle($registration);

            // `IssueTicket` a genere et sauvegarde la paire de cles sur sa propre instance de
            // l'evenement (chargee via la relation de l'inscription) : celle du test, gardee en
            // memoire depuis avant, ne la voit pas sans etre rechargee.
            return ['event' => $event->fresh(), 'ticket' => $ticket, 'token' => $ticket->signedToken()];
        });
    }

    private function openEvent(Tenant $tenant): Event
    {
        return $tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    public function test_accepte_un_billet_valide(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->openEvent($tenant);
        ['event' => $event, 'token' => $token] = $this->confirmedTicket($tenant, $event);

        $outcome = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $token, $owner));

        $this->assertSame(ScanResult::Accepted, $outcome['result']);
        $this->assertNotNull($outcome['registration']);
        $this->assertSame(1, $outcome['registration']['partySize']);
    }

    public function test_signale_un_billet_deja_scanne_avec_l_heure_et_l_agent_du_premier_passage(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $secondAgent = User::factory()->withTwoFactor()->create(['name' => 'Deuxieme Agent']);
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->openEvent($tenant);
        ['event' => $event, 'token' => $token] = $this->confirmedTicket($tenant, $event);

        $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $token, $owner));
        $outcome = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $token, $secondAgent));

        $this->assertSame(ScanResult::AlreadyScanned, $outcome['result']);
        $this->assertFalse($outcome['forced']);
        $this->assertNull($outcome['registration']);
        $this->assertNotNull($outcome['firstScannedAt']);
        $this->assertSame($owner->name, $outcome['firstScannedBy']);
    }

    public function test_permet_de_forcer_l_entree_sur_un_billet_deja_scanne(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->openEvent($tenant);
        ['event' => $event, 'token' => $token] = $this->confirmedTicket($tenant, $event);

        $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $token, $owner));
        $outcome = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $token, $owner, force: true));

        $this->assertSame(ScanResult::AlreadyScanned, $outcome['result']);
        $this->assertTrue($outcome['forced']);
        $this->assertNotNull($outcome['registration']);

        $forcedEvent = $tenant->asCurrent(fn () => ScanEvent::where('forced', true)->first());
        $this->assertNotNull($forcedEvent);
        $this->assertSame(ScanResult::AlreadyScanned, $forcedEvent->result);
    }

    public function test_refuse_un_jeton_falsifie(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->openEvent($tenant);
        ['event' => $event] = $this->confirmedTicket($tenant, $event);

        $outcome = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, 'jeton-invente', $owner));

        $this->assertSame(ScanResult::Refused, $outcome['result']);
        $this->assertNull($outcome['registration']);
    }

    public function test_refuse_un_billet_presente_a_un_autre_evenement_du_meme_locataire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->openEvent($tenant);
        ['event' => $event, 'token' => $token] = $this->confirmedTicket($tenant, $event);
        $otherEvent = $this->openEvent($tenant);

        $outcome = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($otherEvent, $token, $owner));

        $this->assertSame(ScanResult::Refused, $outcome['result']);
    }

    public function test_refuse_un_billet_apres_cloture_de_l_evenement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->openEvent($tenant);
        ['event' => $event, 'token' => $token] = $this->confirmedTicket($tenant, $event);

        $tenant->asCurrent(fn () => app(SaveEvent::class)->close($event));

        $outcome = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $token, $owner));

        $this->assertSame(ScanResult::Refused, $outcome['result']);
    }

    public function test_chaque_tentative_est_journalisee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->openEvent($tenant);
        ['event' => $event, 'token' => $token] = $this->confirmedTicket($tenant, $event);

        $tenant->asCurrent(function () use ($event, $token, $owner) {
            app(ScanTicket::class)->handle($event, $token, $owner);
            app(ScanTicket::class)->handle($event, $token, $owner);
            app(ScanTicket::class)->handle($event, 'jeton-invente', $owner);
        });

        $results = $tenant->asCurrent(fn () => ScanEvent::orderBy('id')->pluck('result'));

        $this->assertEquals([ScanResult::Accepted, ScanResult::AlreadyScanned, ScanResult::Refused], $results->all());
    }
}
