<?php

namespace Tests\Feature\Scan;

use App\Actions\Scan\ScanTicket;
use App\Actions\Seating\AssignTable;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\RegistrationStatus;
use App\Enums\ScanResult;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketRevocationList;
use App\Support\TicketToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cycle de vie du jeton QR (SECURITY.md C2) : date d'expiration et version de cle dans la charge
 * signee, rotation de la cle par evenement, liste de revocation signee pour le scan hors ligne.
 */
class TicketTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user): Tenant
    {
        return app(CreateTenant::class)->handle($user, 'Association Convive');
    }

    /**
     * @return array{event: Event, ticket: Ticket, registration: Registration, token: string}
     */
    private function confirmedTicket(Tenant $tenant): array
    {
        return $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create(['starts_at' => now()->addDays(10)]);
            SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]);
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]);
            app(AssignTable::class)->handle($registration);
            $ticket = app(IssueTicket::class)->handle($registration);

            return [
                'event' => $event->fresh(),
                'ticket' => $ticket,
                'registration' => $registration,
                'token' => $ticket->signedToken(),
            ];
        });
    }

    public function test_le_jeton_porte_une_date_d_expiration_et_la_version_de_cle(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'token' => $token] = $this->confirmedTicket($tenant);

        $payload = TicketToken::verify($token, $event->qr_public_key);

        $this->assertNotNull($payload);
        $this->assertSame($event->qr_key_version, $payload['key_version']);
        $this->assertSame($event->ticketValidUntil()?->getTimestamp(), $payload['not_after']);
    }

    public function test_refuse_un_billet_presente_apres_sa_date_d_expiration(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'token' => $token] = $this->confirmedTicket($tenant);

        $this->travelTo($event->ticketValidUntil()->addMinute());

        $outcome = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $token, $owner));

        $this->assertSame(ScanResult::Refused, $outcome['result']);
    }

    public function test_un_billet_reste_valide_si_l_evenement_est_repousse_apres_son_emission(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'token' => $token] = $this->confirmedTicket($tenant);

        // Le billet deja telecharge porte l'ancienne echeance ; l'evenement repousse d'une semaine
        // ne doit pas refuser l'invite a la porte.
        $event = $tenant->asCurrent(function () use ($event) {
            $event->update(['starts_at' => $event->starts_at->addWeek()]);

            return $event->fresh();
        });

        $this->travelTo($event->starts_at->addHour());

        $outcome = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $token, $owner));

        $this->assertSame(ScanResult::Accepted, $outcome['result']);
    }

    public function test_la_rotation_de_cle_invalide_les_anciens_jetons_et_signe_les_nouveaux(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'ticket' => $ticket, 'token' => $oldToken] = $this->confirmedTicket($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.scan.rotate-key', [$tenant, $event]))
            ->assertRedirect();

        [$event, $newToken] = $tenant->asCurrent(function () use ($event, $ticket) {
            return [$event->fresh(), $ticket->fresh()->signedToken()];
        });

        $this->assertSame(2, $event->qr_key_version);
        $this->assertNotSame($oldToken, $newToken);

        $refused = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $oldToken, $owner));
        $this->assertSame(ScanResult::Refused, $refused['result']);

        $accepted = $tenant->asCurrent(fn () => app(ScanTicket::class)->handle($event, $newToken, $owner));
        $this->assertSame(ScanResult::Accepted, $accepted['result']);
    }

    public function test_la_rotation_de_cle_est_journalisee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->confirmedTicket($tenant);

        $this->actingAs($owner)->post(route('tenants.events.scan.rotate-key', [$tenant, $event]));

        $tenant->asCurrent(function () {
            $this->assertDatabaseHas('activity_log', ['description' => 'event.ticket_key_rotated']);
        });
    }

    public function test_la_rotation_de_cle_est_refusee_sans_la_permission_de_modifier_l_evenement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->confirmedTicket($tenant);

        $agent = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $agent, [TenantPermission::ScanPerform]);

        $this->actingAs($agent)
            ->post(route('tenants.events.scan.rotate-key', [$tenant, $event]))
            ->assertForbidden();

        $this->assertSame(1, $tenant->asCurrent(fn () => $event->fresh()->qr_key_version));
    }

    public function test_la_rotation_de_cle_d_un_autre_locataire_repond_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->confirmedTicket($tenant);

        $stranger = User::factory()->withTwoFactor()->create();
        $strangerTenant = $this->tenantOwnedBy($stranger);

        $this->actingAs($stranger)
            ->post(route('tenants.events.scan.rotate-key', [$tenant, $event]))
            ->assertNotFound();

        $this->assertNotNull($strangerTenant);
    }

    public function test_la_liste_de_revocation_est_signee_et_liste_les_billets_annules(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'registration' => $registration] = $this->confirmedTicket($tenant);

        $list = $tenant->asCurrent(function () use ($event, $registration) {
            $registration->update(['status' => RegistrationStatus::Cancelled]);

            return TicketRevocationList::signedFor($event->fresh());
        });

        $payload = TicketToken::verify($list, $event->qr_public_key);

        $this->assertNotNull($payload);
        $this->assertSame($event->id, $payload['event_id']);
        $this->assertSame([$registration->id], $payload['revoked']);
    }

    public function test_la_liste_de_revocation_ne_liste_pas_un_billet_valide(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->confirmedTicket($tenant);

        $list = $tenant->asCurrent(fn () => TicketRevocationList::signedFor($event));

        $this->assertSame([], TicketToken::verify($list, $event->qr_public_key)['revoked']);
    }

    public function test_la_page_de_scan_transmet_la_liste_de_revocation_et_l_echeance(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->confirmedTicket($tenant);

        $this->actingAs($owner)
            ->get(route('tenants.events.scan.index', [$tenant, $event]))
            ->assertInertia(fn ($page) => $page
                ->where('event.qrKeyVersion', 1)
                ->where('event.ticketValidUntil', $event->ticketValidUntil()->getTimestamp())
                ->has('revocationList'));
    }
}
