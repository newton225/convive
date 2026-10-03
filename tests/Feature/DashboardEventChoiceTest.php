<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Le choix de l'evenement resume par le tableau de bord (demande du proprietaire du projet,
 * 2026-10-03) : par defaut, l'evenement ouvert ou en cours le plus proche, selon la regle deja en
 * place ; un selecteur en haut de l'ecran permet d'en regarder un autre.
 */
class DashboardEventChoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $soonest;

    private Event $later;

    private Event $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        [$this->soonest, $this->later, $this->draft] = $this->tenant->asCurrent(fn () => [
            Event::factory()->open()->create(['name' => 'Diner de gala', 'starts_at' => now()->addWeek()]),
            Event::factory()->open()->create(['name' => 'Seminaire', 'starts_at' => now()->addMonth()]),
            Event::factory()->create(['name' => 'Brouillon', 'status' => 'draft']),
        ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function dashboard(?int $eventId = null): TestResponse
    {
        // L'organisation dite explicitement : creer celle d'un autre compte change l'organisation
        // par defaut des adresses dans le processus de test.
        $parameters = ['current_tenant' => $this->tenant->slug, ...($eventId === null ? [] : ['event' => $eventId])];

        return $this->actingAs($this->owner)->get(route('dashboard', $parameters));
    }

    public function test_par_defaut_le_tableau_de_bord_resume_l_evenement_ouvert_le_plus_proche(): void
    {
        $this->dashboard()->assertInertia(fn (Assert $page) => $page
            ->where('overview.eventId', $this->soonest->id)
            ->where('selectedEventId', null),
        );
    }

    public function test_on_choisit_un_autre_evenement_y_compris_un_brouillon(): void
    {
        $this->dashboard($this->later->id)->assertInertia(fn (Assert $page) => $page
            ->where('overview.eventId', $this->later->id)
            ->where('overview.eventName', 'Seminaire')
            ->where('selectedEventId', $this->later->id),
        );

        $this->dashboard($this->draft->id)->assertInertia(fn (Assert $page) => $page
            ->where('overview.eventId', $this->draft->id),
        );
    }

    public function test_le_selecteur_propose_tous_les_evenements_de_l_organisation(): void
    {
        $this->dashboard()->assertInertia(fn (Assert $page) => $page
            ->has('eventChoices', 3)
            ->where('eventChoices.0.id', $this->soonest->id)
            ->where('eventChoices.0.name', 'Diner de gala'),
        );
    }

    public function test_un_evenement_inconnu_ramene_au_choix_par_defaut(): void
    {
        $this->dashboard(999999)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('overview.eventId', $this->soonest->id)
            ->where('selectedEventId', null),
        );
    }

    public function test_l_evenement_d_une_autre_organisation_n_est_jamais_resume(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();
        $other = app(CreateTenant::class)->handle($stranger, 'Autre organisation');
        $foreign = $other->asCurrent(fn () => Event::factory()->open()->create(['name' => 'Evenement etranger']));

        // Meme identifiant possible dans une autre base : seule la base de l'organisation courante
        // est lue, l'evenement etranger n'y existe pas sous ce nom.
        $this->dashboard($foreign->id)->assertInertia(fn (Assert $page) => $page
            ->where('overview.eventName', fn (string $name) => $name !== 'Evenement etranger'),
        );
    }
}
