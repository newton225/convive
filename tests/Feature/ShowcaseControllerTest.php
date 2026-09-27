<?php

namespace Tests\Feature;

use App\Models\ShowcaseEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La vitrine des evenements a la une (README ecran 1, CLAUDE.md « Annonce sur le site
 * produit ») : lit uniquement la table centrale, jamais les bases des locataires.
 */
class ShowcaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_vitrine_affiche_les_evenements_annonces_du_plus_recent_au_plus_ancien(): void
    {
        $tenant = Tenant::factory()->create();

        $older = ShowcaseEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Diner du mois dernier',
            'announced_at' => now()->subDays(3),
        ]);
        $newer = ShowcaseEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Diner de cette semaine',
            'announced_at' => now()->subHour(),
        ]);

        $this->get(route('showcase.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('showcase')
                ->has('events', 2)
                ->where('events.0.name', $newer->name)
                ->where('events.1.name', $older->name),
            );
    }

    public function test_la_vitrine_est_vide_sans_evenement_annonce(): void
    {
        $this->get(route('showcase.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('events', 0));
    }

    public function test_le_lien_de_la_vitrine_porte_l_adresse_complete_avec_jeton(): void
    {
        $tenant = Tenant::factory()->create();
        $token = bin2hex(random_bytes(32));

        ShowcaseEvent::factory()->create([
            'tenant_id' => $tenant->id,
            'public_url' => "https://convive-ci.convive.test/e/{$token}",
        ]);

        $this->get(route('showcase.index'))
            ->assertInertia(fn ($page) => $page
                ->where('events.0.publicUrl', "https://convive-ci.convive.test/e/{$token}"),
            );
    }
}
