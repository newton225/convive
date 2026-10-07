<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La file de preuves, paginee, cherchee et filtree par le serveur (TODO du 2026-10-07, point 11).
 */
class ProofQueuePaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    /**
     * @param  array<string, mixed>  $registration
     * @param  array<string, mixed>  $proof
     */
    private function proof(array $registration = [], array $proof = []): PaymentProof
    {
        return $this->tenant->asCurrent(function () use ($registration, $proof) {
            $submitted = Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id, ...$registration]);

            return PaymentProof::factory()->create(['registration_id' => $submitted->id, ...$proof]);
        });
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function queue(array $query = []): TestResponse
    {
        return $this->actingAs($this->owner)->get(route('tenants.events.proofs.index', [$this->tenant, $this->event, ...$query]));
    }

    public function test_la_file_n_envoie_que_la_page_demandee(): void
    {
        for ($i = 0; $i < 27; $i++) {
            $this->proof();
        }

        $this->queue()->assertInertia(fn (Assert $page) => $page
            ->has('rows', 25)
            ->where('meta.total', 27)
            ->where('meta.lastPage', 2));

        $this->queue(['page' => 2])->assertInertia(fn (Assert $page) => $page
            ->has('rows', 2)
            ->where('meta.currentPage', 2));
    }

    public function test_la_recherche_trouve_le_nom_sans_les_accents(): void
    {
        $this->proof(['name' => 'Aya Kouamé']);
        $this->proof(['name' => 'Koffi Brou']);

        $this->queue(['filter' => ['search' => 'kouame']])->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Aya Kouamé')
            ->where('filters.search', 'kouame'));
    }

    public function test_la_recherche_trouve_la_reference_de_la_transaction(): void
    {
        $this->proof(['name' => 'Aya Kouame'], ['reference' => 'TX-ABC-123']);
        $this->proof(['name' => 'Koffi Brou'], ['reference' => 'TX-ZZZ-999']);

        $this->queue(['filter' => ['search' => 'abc-123']])->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Aya Kouame'));
    }

    public function test_le_filtre_des_anomalies_se_fait_cote_serveur(): void
    {
        // Deux preuves portent la meme reference : chacune signale l'autre.
        $this->proof(['name' => 'Aya'], ['reference' => 'TX-DOUBLE']);
        $this->proof(['name' => 'Koffi'], ['reference' => 'TX-DOUBLE']);
        $this->proof(['name' => 'Mariam'], ['reference' => 'TX-UNIQUE']);

        $this->queue(['filter' => ['signal' => 'anomaly']])->assertInertia(fn (Assert $page) => $page
            ->has('rows', 2)
            ->where('meta.total', 2)
            ->where('filters.signal', 'anomaly'));

        $this->queue(['filter' => ['signal' => 'clean']])->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Mariam'));
    }

    public function test_le_filtre_et_la_recherche_restent_d_une_page_a_l_autre(): void
    {
        for ($i = 0; $i < 27; $i++) {
            $this->proof(['name' => "Gala {$i}"], ['reference' => 'TX-MEME']);
        }

        $this->queue(['filter' => ['signal' => 'anomaly', 'search' => 'gala'], 'page' => 2])->assertInertia(fn (Assert $page) => $page
            ->has('rows', 2)
            ->where('meta.currentPage', 2)
            ->where('filters.signal', 'anomaly')
            ->where('filters.search', 'gala'));
    }

    public function test_le_tri_par_defaut_traite_la_file_dans_l_ordre_d_arrivee(): void
    {
        $this->travelTo(now()->subHour());
        $this->proof(['name' => 'Premier arrive']);
        $this->travelBack();
        $this->proof(['name' => 'Dernier arrive']);

        $this->queue()->assertInertia(fn (Assert $page) => $page->where('rows.0.name', 'Premier arrive'));
        $this->queue(['sort' => '-submitted_at'])->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.name', 'Dernier arrive')
            ->where('filters.sort', '-submitted_at'));
    }

    public function test_une_file_vide_le_dit(): void
    {
        $this->queue()->assertInertia(fn (Assert $page) => $page
            ->has('rows', 0)
            ->where('hasProofs', false));
    }
}
