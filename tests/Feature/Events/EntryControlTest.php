<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\EventStatus;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le raccourci « Controle a l'entree » du menu (README ecran 26) : l'hotesse arrive sur le scan
 * du soir sans passer par la liste des evenements.
 */
class EntryControlTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    private function eventOn(string $startsAt, array $attributes = []): Event
    {
        return $this->tenant->asCurrent(fn () => Event::factory()->published()->create([
            'starts_at' => $startsAt,
            ...$attributes,
        ]));
    }

    public function test_un_seul_evenement_du_jour_ouvre_directement_son_scan(): void
    {
        $event = $this->eventOn(now()->setTime(19, 0)->toDateTimeString());

        $this->actingAs($this->owner)
            ->get(route('tenants.entry-control', $this->tenant))
            ->assertRedirect(route('tenants.events.scan.index', [$this->tenant, $event]));
    }

    public function test_plusieurs_evenements_du_jour_proposent_un_choix(): void
    {
        $this->eventOn(now()->setTime(12, 0)->toDateTimeString());
        $this->eventOn(now()->setTime(20, 0)->toDateTimeString());

        $this->actingAs($this->owner)
            ->get(route('tenants.entry-control', $this->tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/entry-control')
                ->has('events', 2));
    }

    public function test_un_evenement_en_cours_compte_meme_commence_la_veille(): void
    {
        $event = $this->eventOn(now()->subDay()->setTime(22, 0)->toDateTimeString(), ['status' => EventStatus::Ongoing]);

        $this->actingAs($this->owner)
            ->get(route('tenants.entry-control', $this->tenant))
            ->assertRedirect(route('tenants.events.scan.index', [$this->tenant, $event]));
    }

    public function test_les_evenements_clos_non_publies_ou_d_un_autre_jour_sont_ignores(): void
    {
        $this->eventOn(now()->setTime(19, 0)->toDateTimeString(), ['status' => EventStatus::Closed]);
        $this->eventOn(now()->addDays(3)->toDateTimeString());
        $this->tenant->asCurrent(fn () => Event::factory()->open()->create(['starts_at' => now()->setTime(19, 0)]));

        $this->actingAs($this->owner)
            ->get(route('tenants.entry-control', $this->tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/entry-control')
                ->has('events', 0));
    }

    public function test_un_membre_sans_la_permission_de_scanner_est_refuse(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.entry-control', $this->tenant))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.entry-control', $this->tenant))
            ->assertNotFound();
    }
}
