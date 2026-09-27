<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Partir d'un modele » a la creation d'un evenement (prototype Convive.dc.html) : un evenement
 * existant pre-remplit tables, tarif, accompagnateurs et comptes de versement. Le nom et les dates
 * restent a saisir, ils dependent du nouvel evenement.
 */
class EventTemplateTest extends TestCase
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

    public function test_l_ecran_de_creation_propose_les_evenements_existants_comme_modeles(): void
    {
        $this->tenant->asCurrent(fn () => Event::factory()->create(['name' => 'Gala des Sentinelles 2026', 'table_count' => 22]));

        $this->actingAs($this->owner)
            ->get(route('tenants.events.create', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('templates.0.name', 'Gala des Sentinelles 2026')
                ->where('templates.0.tableCount', 22)
                ->where('template', null));
    }

    public function test_partir_d_un_modele_pre_remplit_le_formulaire_sans_le_nom_ni_les_dates(): void
    {
        [$source, $accountId] = $this->tenant->asCurrent(function () {
            $account = PaymentAccount::factory()->create();
            $source = Event::factory()->create([
                'name' => 'Gala des Sentinelles 2026',
                'table_count' => 22,
                'seats_per_table' => 10,
                'price_per_person' => 25000,
                'companion_limit' => 4,
                'starts_at' => now()->addMonth(),
            ]);
            $source->paymentAccounts()->sync([$account->id]);

            return [$source, $account->id];
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.create', ['tenant' => $this->tenant, 'from' => $source->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('template.sourceName', 'Gala des Sentinelles 2026')
                ->where('template.tableCount', 22)
                ->where('template.seatsPerTable', 10)
                ->where('template.pricePerPerson', 25000)
                ->where('template.companionLimit', 4)
                ->where('template.paymentAccountIds', [$accountId])
                ->missing('template.name')
                ->missing('template.startsAtLocal'));
    }

    public function test_un_modele_introuvable_laisse_le_formulaire_vierge(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.events.create', ['tenant' => $this->tenant, 'from' => 9999]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('template', null));
    }
}
