<?php

namespace Tests\Feature;

use App\Models\ShowcaseEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La vitrine publique, paginee et cherchee par le serveur (TODO du 2026-10-07, point 11).
 */
class ShowcasePaginationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    public function test_la_vitrine_n_envoie_que_la_page_demandee(): void
    {
        ShowcaseEvent::factory()->count(30)->create(['tenant_id' => $this->tenant->id]);

        $this->get(route('showcase.index'))->assertInertia(fn (Assert $page) => $page
            ->has('events', 24)
            ->where('meta.total', 30)
            ->where('meta.lastPage', 2));

        $this->get(route('showcase.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->has('events', 6)
            ->where('meta.currentPage', 2));
    }

    public function test_la_recherche_porte_sur_l_evenement_et_l_organisation_sans_les_accents(): void
    {
        ShowcaseEvent::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Gala', 'organisation_name' => 'Église de Cocody']);
        ShowcaseEvent::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Diner Kouamé', 'organisation_name' => 'Association']);
        ShowcaseEvent::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Seminaire', 'organisation_name' => 'Entreprise']);

        $this->get(route('showcase.index', ['filter' => ['search' => 'eglise']]))->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Gala')
            ->where('filters.search', 'eglise'));

        $this->get(route('showcase.index', ['filter' => ['search' => 'kouame']]))->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Diner Kouamé'));
    }

    public function test_la_recherche_reste_d_une_page_a_l_autre(): void
    {
        ShowcaseEvent::factory()->count(30)->create(['tenant_id' => $this->tenant->id, 'name' => 'Gala']);

        $this->get(route('showcase.index', ['filter' => ['search' => 'gala'], 'page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->has('events', 6)
            ->where('meta.currentPage', 2)
            ->where('filters.search', 'gala'));
    }

    public function test_une_vitrine_sans_annonce_le_dit(): void
    {
        $this->get(route('showcase.index'))->assertInertia(fn (Assert $page) => $page
            ->has('events', 0)
            ->where('hasEvents', false));
    }
}
