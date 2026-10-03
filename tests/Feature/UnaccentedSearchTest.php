<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Search\UnaccentedSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Toute recherche ignore les accents et la casse (decision du proprietaire du projet, 2026-10-02) :
 * un agent ou un tresorier tape « kouame » sur un telephone, l'invite s'appelle « Kouamé ».
 */
class UnaccentedSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create(['name' => 'Responsable Convive']);
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function registrationsMatching(array $names, string $term): array
    {
        return $this->tenant->asCurrent(function () use ($names, $term) {
            $event = Event::factory()->open()->create();

            foreach ($names as $name) {
                Registration::factory()->create(['event_id' => $event->id, 'name' => $name]);
            }

            // Seulement cet evenement : chaque appel cree le sien dans la meme organisation.
            $query = Registration::where('event_id', $event->id);
            UnaccentedSearch::apply($query, ['name'], $term);

            return $query->orderBy('id')->pluck('name')->all();
        });
    }

    public function test_une_recherche_sans_accent_trouve_un_nom_accentue(): void
    {
        $this->assertSame(
            ['Yao Kouamé'],
            $this->registrationsMatching(['Yao Kouamé', 'Aya Kouassi'], 'kouame'),
        );
    }

    public function test_une_recherche_accentuee_trouve_un_nom_sans_accent(): void
    {
        $this->assertSame(
            ['Helene Brou'],
            $this->registrationsMatching(['Helene Brou', 'Aya Kouassi'], 'Hélène'),
        );
    }

    public function test_la_casse_est_ignoree_y_compris_sur_une_majuscule_accentuee(): void
    {
        $this->assertSame(
            ['ÉLODIE N’GUESSAN'],
            $this->registrationsMatching(['ÉLODIE N’GUESSAN', 'Aya Kouassi'], 'élodie'),
        );
    }

    public function test_une_saisie_faite_de_seuls_jokers_ne_trouve_rien_plutot_que_tout(): void
    {
        $this->assertSame([], $this->registrationsMatching(['Aya Kouassi'], '%'));
        $this->assertSame([], $this->registrationsMatching(['Aya Kouassi'], '  '));
    }

    public function test_un_joker_ou_une_barre_oblique_dans_la_saisie_ne_casse_pas_la_recherche(): void
    {
        $this->assertSame(['Kofi Diallo'], $this->registrationsMatching(['Kofi Diallo', 'Aya Kouassi'], 'kofi%'));
        $this->assertSame(['Kofi Diallo'], $this->registrationsMatching(['Kofi Diallo', 'Aya Kouassi'], 'kofi\\'));
    }

    public function test_la_recherche_porte_sur_plusieurs_colonnes_sans_elargir_les_autres_criteres(): void
    {
        $names = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $other = Event::factory()->open()->create();

            Registration::factory()->create(['event_id' => $event->id, 'name' => 'Yao Kouamé', 'email' => 'yao@example.com']);
            Registration::factory()->create(['event_id' => $event->id, 'name' => 'Aya Kouassi', 'email' => 'kouame.aya@example.com']);
            Registration::factory()->create(['event_id' => $other->id, 'name' => 'Koffi Kouamé', 'email' => 'koffi@example.com']);

            $query = Registration::where('event_id', $event->id);
            UnaccentedSearch::apply($query, ['name', 'email'], 'kouame');

            return $query->orderBy('id')->pluck('name')->all();
        });

        $this->assertSame(['Yao Kouamé', 'Aya Kouassi'], $names);
    }

    public function test_la_base_centrale_cherche_aussi_sans_accent(): void
    {
        User::factory()->create(['name' => 'Aimée Besson']);

        $query = User::query();
        UnaccentedSearch::apply($query, ['name'], 'aimee');

        $this->assertSame(['Aimée Besson'], $query->pluck('name')->all());
    }

    public function test_la_base_d_inscrits_trouve_un_nom_accentue(): void
    {
        $event = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();

            Registration::factory()->create(['event_id' => $event->id, 'name' => 'Yao Kouamé']);
            Registration::factory()->create(['event_id' => $event->id, 'name' => 'Kofi Diallo']);

            return $event;
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $event]).'?filter[search]=kouame')
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.name', 'Yao Kouamé'));
    }

    public function test_l_historique_des_actions_trouve_un_membre_au_nom_accentue(): void
    {
        $member = User::factory()->withTwoFactor()->create(['name' => 'Aimée Besson']);

        $this->tenant->asCurrent(fn () => activity()->causedBy($member)->log('event.created'));

        $this->actingAs($this->owner)
            ->get(route('tenants.audit.index', $this->tenant).'?filter[search]=aimee')
            ->assertInertia(fn ($page) => $page->has('entries', 1));
    }
}
