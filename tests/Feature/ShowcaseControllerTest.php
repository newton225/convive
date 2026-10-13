<?php

namespace Tests\Feature;

use App\Models\Event;
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

    public function test_un_evenement_commence_reste_a_la_vitrine_jusqu_a_sa_fin(): void
    {
        ShowcaseEvent::factory()->create([
            'name' => 'Salon en cours',
            'starts_at' => now()->subDay(),
            'listed_until' => now()->addDay(),
        ]);
        ShowcaseEvent::factory()->create([
            'name' => 'Salon termine',
            'starts_at' => now()->subDays(3),
            'listed_until' => now()->subMinute(),
        ]);

        $this->get(route('showcase.index'))
            ->assertInertia(fn ($page) => $page
                ->has('events', 1)
                ->where('events.0.name', 'Salon en cours'),
            );
    }

    public function test_la_vitrine_s_arrete_a_la_date_limite_des_inscriptions_quand_elle_precede_la_fin(): void
    {
        $event = new Event([
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'registration_deadline' => now()->addDay(),
        ]);

        $this->assertTrue($event->showcaseUntil()->equalTo($event->registration_deadline));
    }

    public function test_la_vitrine_suit_la_fin_ou_a_defaut_le_debut_sans_date_limite(): void
    {
        $withEnd = new Event(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $withoutEnd = new Event(['starts_at' => now()->addDay()]);

        $this->assertTrue($withEnd->showcaseUntil()->equalTo($withEnd->ends_at));
        $this->assertTrue($withoutEnd->showcaseUntil()->equalTo($withoutEnd->starts_at));
    }

    public function test_une_date_limite_posterieure_a_la_fin_ne_prolonge_pas_la_vitrine(): void
    {
        $event = new Event([
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'registration_deadline' => now()->addDays(10),
        ]);

        $this->assertTrue($event->showcaseUntil()->equalTo($event->ends_at));
    }

    public function test_on_n_annonce_pas_un_evenement_dont_les_inscriptions_sont_closes(): void
    {
        $event = new Event([
            'starts_at' => now()->addWeek(),
            'registration_deadline' => now()->subHour(),
        ]);

        $this->assertContains('past', $event->missingBeforeAnnouncing());
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
