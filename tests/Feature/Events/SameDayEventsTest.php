<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Deux evenements de la meme organisation le meme jour, en deux lieux (README ecran 26). Chaque
 * billet est signe pour un seul evenement : un billet de Bouake scanne a Abidjan reste refuse, mais
 * l'agent apprend ou l'invite est attendu, et personne n'est alerte comme d'une fraude.
 */
class SameDayEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $abidjan;

    private Event $bouake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        [$this->abidjan, $this->bouake] = $this->tenant->asCurrent(fn () => [
            Event::factory()->published()->create(['name' => 'Diner de gala', 'venue' => 'Hotel Ivoire', 'starts_at' => now()->setTime(19, 0)]),
            Event::factory()->published()->create(['name' => 'Diner de gala', 'venue' => 'Salle Kennedy, Bouake', 'starts_at' => now()->setTime(19, 30)]),
        ]);
    }

    private function ticketFor(Event $event): string
    {
        return $this->tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]);

            return app(IssueTicket::class)->handle($registration)->signedToken();
        });
    }

    private function scanAt(Event $event, string $token): TestResponse
    {
        return $this->actingAs($this->owner)
            ->followingRedirects()
            ->post(route('tenants.events.scan.verify', [$this->tenant, $event]), ['token' => $token]);
    }

    private function watcher(): User
    {
        $watcher = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $watcher, [TenantPermission::ScanLogView], 'Surveillance');

        return $watcher;
    }

    public function test_un_billet_de_l_autre_evenement_du_jour_dit_ou_l_invite_est_attendu(): void
    {
        $this->scanAt($this->abidjan, $this->ticketFor($this->bouake))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.result', 'refused')
                ->where('result.otherEvent.name', 'Diner de gala')
                ->where('result.otherEvent.venue', 'Salle Kennedy, Bouake')
                ->where('result.registration', null),
            );
    }

    public function test_un_billet_de_l_autre_evenement_n_alerte_pas_comme_une_fraude(): void
    {
        Notification::fake();
        $watcher = $this->watcher();

        $this->scanAt($this->abidjan, $this->ticketFor($this->bouake));

        Notification::assertNotSentTo($watcher, TenantAlert::class);
    }

    public function test_un_jeton_falsifie_reste_refuse_sans_rien_reveler_et_alerte(): void
    {
        Notification::fake();
        $watcher = $this->watcher();

        [$payload, $signature] = explode('.', $this->ticketFor($this->bouake));
        $forged = $payload.'.'.strrev($signature);

        $this->scanAt($this->abidjan, $forged)
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.result', 'refused')
                ->where('result.otherEvent', null),
            );

        Notification::assertSentTo($watcher, TenantAlert::class);
    }

    public function test_l_ecran_de_scan_montre_le_lieu_et_les_autres_evenements_du_jour(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.events.scan.index', [$this->tenant, $this->abidjan]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.venue', 'Hotel Ivoire')
                ->has('event.startsAt')
                ->has('otherEventsToday', 1)
                ->where('otherEventsToday.0.venue', 'Salle Kennedy, Bouake'),
            );
    }

    public function test_le_choix_de_l_evenement_s_affiche_a_la_demande_meme_s_il_n_y_en_a_qu_un(): void
    {
        $this->tenant->asCurrent(fn () => $this->bouake->delete());

        $this->actingAs($this->owner)
            ->get(route('tenants.entry-control', $this->tenant))
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->get(route('tenants.entry-control', [$this->tenant, 'choose' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/entry-control')
                ->has('events', 1),
            );
    }
}
