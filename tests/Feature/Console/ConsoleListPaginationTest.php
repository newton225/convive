<?php

namespace Tests\Feature\Console;

use App\Models\ConsoleActionLog;
use App\Models\MessageLog;
use App\Models\SecurityEvent;
use App\Models\ShowcaseEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Les listes de la console, paginees par le serveur (TODO du 2026-10-07, point 11). Les journaux
 * n'etaient montres que jusqu'a un plafond : au-dela, les entrees les plus anciennes etaient
 * invisibles. Paginees, elles restent toutes atteignables.
 */
class ConsoleListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
    }

    public function test_les_organisations_sont_paginees(): void
    {
        // Le fondateur a deja son organisation personnelle : 29 de plus font 30 au total.
        Tenant::factory()->count(29)->create();

        $this->actingAs($this->founder)
            ->get(route('console.organisations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('organisations', 25)
                ->where('meta.total', 30));

        $this->actingAs($this->founder)
            ->get(route('console.organisations.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('organisations', 5)
                ->where('meta.currentPage', 2));
    }

    public function test_la_recherche_et_le_statut_des_organisations_se_font_cote_serveur(): void
    {
        Tenant::factory()->create(['name' => 'Église de Cocody']);
        Tenant::factory()->create(['name' => 'Association Kouamé']);
        Tenant::factory()->create(['name' => 'Entreprise'])->delete();

        $this->actingAs($this->founder)
            ->get(route('console.organisations.index', ['filter' => ['search' => 'eglise']]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('organisations', 1)
                ->where('organisations.0.name', 'Église de Cocody')
                ->where('filters.search', 'eglise'));

        $this->actingAs($this->founder)
            ->get(route('console.organisations.index', ['filter' => ['status' => 'deleted_by_owner']]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('organisations', 1)
                ->where('organisations.0.name', 'Entreprise')
                ->where('filters.status', 'deleted_by_owner'));
    }

    public function test_les_resultats_de_recherche_de_comptes_sont_pagines(): void
    {
        User::factory()->count(30)->create(['name' => 'Aya Kouamé']);

        $this->actingAs($this->founder)
            ->get(route('console.accounts.index', ['q' => 'kouame', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('results', 5)
                ->where('meta.total', 30)
                ->where('meta.currentPage', 2)
                ->where('search', 'kouame'));
    }

    public function test_le_journal_central_n_a_plus_de_plafond(): void
    {
        for ($i = 0; $i < 30; $i++) {
            ConsoleActionLog::create(['type' => 'plan.updated', 'actor_name' => 'Fondateur', 'created_at' => now()->subMinutes($i)]);
        }

        $this->actingAs($this->founder)
            ->get(route('console.audit', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries', 5)
                ->where('meta.total', 30));
    }

    public function test_le_journal_de_securite_est_pagine(): void
    {
        for ($i = 0; $i < 30; $i++) {
            SecurityEvent::create(['type' => SecurityEvent::RateLimited, 'ip' => '203.0.113.9', 'created_at' => now()->subMinutes($i)]);
        }

        $this->actingAs($this->founder)
            ->get(route('console.security', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('events', 5)
                ->where('eventsMeta.total', 30)
                ->where('eventsMeta.currentPage', 2));
    }

    public function test_le_journal_des_envois_est_pagine(): void
    {
        for ($i = 0; $i < 30; $i++) {
            MessageLog::create(['channel' => 'mail', 'type' => 'invitation_card', 'recipient' => 'invite@example.com', 'simulated' => false, 'created_at' => now()->subMinutes($i)]);
        }

        $this->actingAs($this->founder)
            ->get(route('console.messages', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('messages', 5)
                ->where('messagesMeta.total', 30));
    }

    public function test_les_annonces_de_la_vitrine_sont_paginees(): void
    {
        ShowcaseEvent::factory()->count(30)->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->actingAs($this->founder)
            ->get(route('console.showcase', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('announcements', 5)
                ->where('announcementsMeta.total', 30));
    }
}
