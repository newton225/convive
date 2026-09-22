<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @return array{event: Event, token: string}
     */
    private function eventWithTicket(Tenant $tenant): array
    {
        return $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]);
            $ticket = app(IssueTicket::class)->handle($registration);

            return ['event' => $event, 'token' => $ticket->signedToken()];
        });
    }

    public function test_un_membre_avec_la_permission_voit_l_ecran_de_scan(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->eventWithTicket($tenant);

        $this->actingAs($owner)
            ->get(route('tenants.events.scan.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('events/scan'));
    }

    public function test_un_membre_sans_la_permission_ne_voit_pas_l_ecran_de_scan(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->eventWithTicket($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.scan.index', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_l_ecran_de_scan(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->eventWithTicket($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.scan.index', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_un_membre_avec_la_permission_scanne_un_billet_valide(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'token' => $token] = $this->eventWithTicket($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ScanPerform]);

        $this->actingAs($member)
            ->post(route('tenants.events.scan.verify', [$tenant, $event]), ['token' => $token])
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('result.result', 'accepted'));
    }

    public function test_un_membre_sans_scan_perform_ne_peut_pas_scanner(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'token' => $token] = $this->eventWithTicket($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->post(route('tenants.events.scan.verify', [$tenant, $event]), ['token' => $token])
            ->assertForbidden();
    }

    public function test_forcer_l_entree_exige_la_permission_dediee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'token' => $token] = $this->eventWithTicket($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ScanPerform]);

        // Premier scan, par le proprietaire (qui detient tout le catalogue) : accepte, pour
        // que le second se heurte a « deja scanne ».
        $this->actingAs($owner)
            ->post(route('tenants.events.scan.verify', [$tenant, $event]), ['token' => $token]);

        $this->actingAs($member)
            ->post(route('tenants.events.scan.verify', [$tenant, $event]), ['token' => $token, 'force' => true])
            ->assertForbidden();
    }

    public function test_le_journal_des_passages_n_apparait_qu_avec_la_permission_dediee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'token' => $token] = $this->eventWithTicket($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.scan.verify', [$tenant, $event]), ['token' => $token]);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ScanPerform]);

        $this->actingAs($member)
            ->get(route('tenants.events.scan.index', [$tenant, $event]))
            ->assertInertia(fn ($page) => $page->where('recent', []));

        $this->actingAs($owner)
            ->get(route('tenants.events.scan.index', [$tenant, $event]))
            ->assertInertia(fn ($page) => $page->has('recent', 1));
    }
}
