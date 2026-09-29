<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ReconciliationOutcome;
use App\Models\Event;
use App\Models\StatementImport;
use App\Models\StatementLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les lignes d'un releve (README ecran 19) : recherche, filtre par issue, tri et pagination
 * passent par le serveur (`spatie/laravel-query-builder`), comme la base d'inscrits.
 */
class ReconciliationListTest extends TestCase
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

        $this->tenant->asCurrent(function () {
            $import = StatementImport::factory()->create(['event_id' => $this->event->id]);

            StatementLine::factory()->create([
                'statement_import_id' => $import->id, 'line_number' => 1, 'reference' => 'WV0001',
                'issuer' => 'Aya Kouassi', 'amount' => 20000, 'outcome' => ReconciliationOutcome::Matched,
            ]);
            StatementLine::factory()->create([
                'statement_import_id' => $import->id, 'line_number' => 2, 'reference' => 'OM0002',
                'issuer' => 'Kofi Diallo', 'amount' => 90000, 'outcome' => ReconciliationOutcome::NoRegistration,
            ]);
            StatementLine::factory()->create([
                'statement_import_id' => $import->id, 'line_number' => 3, 'reference' => 'WV0003',
                'issuer' => 'Awa Traore', 'amount' => 5000, 'outcome' => ReconciliationOutcome::AmountMismatch,
            ]);
        });
    }

    private function url(string $query = ''): string
    {
        return route('tenants.events.reconciliation.index', [$this->tenant, $this->event]).$query;
    }

    public function test_par_defaut_les_lignes_suivent_l_ordre_du_releve(): void
    {
        $this->actingAs($this->owner)
            ->get($this->url())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 3)
                ->where('rows.0.lineNumber', 1)
                ->where('rows.2.lineNumber', 3),
            );
    }

    public function test_le_filtre_par_issue_ne_garde_que_les_lignes_concernees(): void
    {
        $this->actingAs($this->owner)
            ->get($this->url('?filter[outcome]=no_registration'))
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.issuer', 'Kofi Diallo')
                ->where('filters.outcome', 'no_registration'),
            );
    }

    public function test_la_recherche_porte_sur_la_reference_et_l_emetteur(): void
    {
        $this->actingAs($this->owner)
            ->get($this->url('?filter[search]=awa'))
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.reference', 'WV0003'));

        $this->actingAs($this->owner)
            ->get($this->url('?filter[search]=OM00'))
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.issuer', 'Kofi Diallo'));
    }

    public function test_le_tri_par_montant_decroissant_ordonne_les_lignes(): void
    {
        $this->actingAs($this->owner)
            ->get($this->url('?sort=-amount'))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.amount', 90000)
                ->where('rows.2.amount', 5000)
                ->where('filters.sort', '-amount'),
            );
    }

    public function test_un_tri_non_autorise_est_refuse(): void
    {
        $this->actingAs($this->owner)
            ->get($this->url('?sort=reference'))
            ->assertStatus(400);
    }
}
