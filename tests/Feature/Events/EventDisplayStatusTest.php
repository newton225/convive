<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'etat affiche d'un evenement est calcule d'apres ses dates (decision du 2026-10-25), comme
 * « Complet » : le statut stocke ne bouge pas, la cloture reste un geste de l'equipe.
 */
class EventDisplayStatusTest extends TestCase
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

    private function event(array $attributes = []): Event
    {
        return $this->tenant->asCurrent(fn () => Event::factory()->open()->create($attributes));
    }

    public function test_un_evenement_a_venir_reste_ouvert(): void
    {
        $event = $this->event(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHours(4)]);

        $this->assertSame(EventStatus::Open, $event->displayStatus());
    }

    public function test_un_evenement_entre_son_debut_et_sa_fin_est_en_cours(): void
    {
        $event = $this->event(['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3)]);

        $this->assertSame(EventStatus::Ongoing, $event->displayStatus());
        $this->assertSame(EventStatus::Open, $event->status);
    }

    public function test_un_evenement_dont_la_fin_est_passee_est_termine_sans_etre_cloture(): void
    {
        $event = $this->event(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHours(4)]);

        $this->assertSame(EventStatus::Closed, $event->displayStatus());
        $this->assertSame(EventStatus::Open, $event->status);
    }

    public function test_sans_fin_l_evenement_est_termine_a_son_debut(): void
    {
        $event = $this->event(['starts_at' => now()->subMinute(), 'ends_at' => null]);

        $this->assertSame(EventStatus::Closed, $event->displayStatus());
    }

    public function test_un_brouillon_dont_la_date_est_passee_reste_brouillon(): void
    {
        $event = $this->tenant->asCurrent(fn () => Event::factory()->create([
            'status' => EventStatus::Draft,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(4),
        ]));

        $this->assertSame(EventStatus::Draft, $event->displayStatus());
    }

    public function test_un_evenement_termine_n_accepte_plus_d_inscription(): void
    {
        $event = $this->event([
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(4),
            'registration_deadline' => null,
        ]);

        $this->tenant->asCurrent(fn () => $this->assertFalse($event->acceptsRegistrations()));
    }

    public function test_la_liste_range_un_evenement_termine_parmi_les_clotures(): void
    {
        $over = $this->event(['name' => 'Gala passe', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHours(4)]);
        $coming = $this->event(['name' => 'Gala a venir', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHours(4)]);

        $this->actingAs($this->owner)
            ->get(route('tenants.events.index', [$this->tenant, 'filter' => ['status' => 'closed']]))
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('events.0.id', $over->id)
                ->where('events.0.displayStatus', 'closed'),
            );

        $this->actingAs($this->owner)
            ->get(route('tenants.events.index', [$this->tenant, 'filter' => ['status' => 'active']]))
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('events.0.id', $coming->id)
                ->where('events.0.displayStatus', 'open'),
            );
    }
}
