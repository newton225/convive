<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La liste des evenements, paginee et filtree par le serveur (TODO du 2026-10-07, point 11).
 */
class EventListPaginationTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $query
     */
    private function list(array $query = []): TestResponse
    {
        return $this->actingAs($this->owner)->get(route('tenants.events.index', [$this->tenant, ...$query]));
    }

    public function test_la_liste_n_envoie_que_la_page_demandee(): void
    {
        $this->tenant->asCurrent(fn () => Event::factory()->count(30)->create(['status' => EventStatus::Open]));

        $this->list()->assertInertia(fn (Assert $page) => $page
            ->has('events', 24)
            ->where('meta.currentPage', 1)
            ->where('meta.lastPage', 2)
            ->where('meta.total', 30));

        $this->list(['page' => 2])->assertInertia(fn (Assert $page) => $page
            ->has('events', 6)
            ->where('meta.currentPage', 2));
    }

    public function test_une_page_au_dela_de_la_derniere_ramene_a_la_derniere(): void
    {
        $this->tenant->asCurrent(fn () => Event::factory()->count(30)->create(['status' => EventStatus::Open]));

        $this->list(['page' => 99])->assertInertia(fn (Assert $page) => $page
            ->where('meta.currentPage', 2)
            ->has('events', 6));

        $this->list(['page' => 0])->assertInertia(fn (Assert $page) => $page->where('meta.currentPage', 1));
    }

    public function test_la_recherche_se_fait_cote_serveur_sans_les_accents(): void
    {
        $this->tenant->asCurrent(function () {
            Event::factory()->create(['name' => 'Gala chez Kouamé', 'status' => EventStatus::Open]);
            Event::factory()->count(3)->create(['name' => 'Seminaire', 'status' => EventStatus::Open]);
        });

        $this->list(['filter' => ['search' => 'kouame']])->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Gala chez Kouamé')
            ->where('filters.search', 'kouame'));
    }

    public function test_le_filtre_et_ses_compteurs_suivent_la_recherche(): void
    {
        $this->tenant->asCurrent(function () {
            Event::factory()->create(['name' => 'Gala 2025', 'status' => EventStatus::Closed]);
            Event::factory()->create(['name' => 'Gala 2026', 'status' => EventStatus::Open]);
            Event::factory()->create(['name' => 'Seminaire', 'status' => EventStatus::Open]);
        });

        // En cours et a venir par defaut.
        $this->list()->assertInertia(fn (Assert $page) => $page
            ->has('events', 2)
            ->where('filters.status', 'active'));

        $this->list(['filter' => ['status' => 'closed', 'search' => 'gala']])->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Gala 2025')
            ->where('counts.active', 1)
            ->where('counts.closed', 1)
            ->where('counts.all', 2));
    }

    public function test_les_liens_de_page_gardent_la_recherche_et_le_filtre(): void
    {
        $this->tenant->asCurrent(fn () => Event::factory()->count(30)->create(['name' => 'Gala', 'status' => EventStatus::Closed]));

        $this->list(['filter' => ['status' => 'closed', 'search' => 'gala'], 'page' => 2])->assertInertia(fn (Assert $page) => $page
            ->where('meta.currentPage', 2)
            ->where('filters.status', 'closed')
            ->where('filters.search', 'gala'));
    }

    public function test_une_organisation_sans_evenement_le_dit(): void
    {
        $this->list()->assertInertia(fn (Assert $page) => $page
            ->has('events', 0)
            ->where('hasEvents', false));
    }
}
