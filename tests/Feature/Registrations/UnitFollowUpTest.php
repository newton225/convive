<?php

namespace Tests\Feature\Registrations;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationCompanion;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Le filtre et le resume par unite sur la liste des inscrits (decision du 2026-10-31).
 */
class UnitFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    private Unit $alpha;

    private Unit $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        $this->tenant->asCurrent(function () {
            $this->event = Event::factory()->open()->create();
            $this->alpha = Unit::factory()->create(['name' => 'ALPHA TEST']);
            $this->beta = Unit::factory()->create(['name' => 'BETA TEST']);
        });
    }

    /**
     * Alpha: Aya (confirmee, 1 accompagnateur de Beta), Bamba (confirmee), Cece (reservation en cours).
     * Beta: Dede (confirmee).
     *
     * @return array<string, Registration>
     */
    private function guests(): array
    {
        return $this->tenant->asCurrent(function () {
            $aya = Registration::factory()->confirmed()->create(['event_id' => $this->event->id, 'name' => 'Aya', 'unit_id' => $this->alpha->id]);
            RegistrationCompanion::factory()->create(['registration_id' => $aya->id, 'unit_id' => $this->beta->id]);
            $bamba = Registration::factory()->confirmed()->create(['event_id' => $this->event->id, 'name' => 'Bamba', 'unit_id' => $this->alpha->id]);
            $cece = Registration::factory()->held()->create(['event_id' => $this->event->id, 'name' => 'Cece', 'unit_id' => $this->alpha->id]);
            $dede = Registration::factory()->confirmed()->create(['event_id' => $this->event->id, 'name' => 'Dede', 'unit_id' => $this->beta->id]);

            return compact('aya', 'bamba', 'cece', 'dede');
        });
    }

    private function list(array $filter = []): TestResponse
    {
        return $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event, 'filter' => $filter]));
    }

    public function test_le_resume_compte_les_dossiers_et_les_personnes_confirmes_par_unite(): void
    {
        $this->guests();

        $this->list()->assertInertia(function ($page) {
            $units = collect($page->toArray()['props']['unitSummary'])->keyBy('name');

            $this->assertSame(2, $units['ALPHA TEST']['registrations']);
            $this->assertSame(2, $units['ALPHA TEST']['people']);
            $this->assertSame(1, $units['BETA TEST']['registrations']);
            $this->assertSame(2, $units['BETA TEST']['people']);
        });
    }

    public function test_le_filtre_garde_les_dossiers_dont_l_invite_ou_un_accompagnateur_est_de_l_unite(): void
    {
        $guests = $this->guests();

        $ids = collect($this->list(['unit' => (string) $this->beta->id])->viewData('page')['props']['rows'])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$guests['aya']->id, $guests['dede']->id], $ids);
    }

    public function test_un_identifiant_d_unite_invalide_ne_filtre_rien(): void
    {
        $guests = $this->guests();

        $this->list(['unit' => 'abc'])->assertInertia(fn ($page) => $page->where('meta.total', count($guests)));
    }

    public function test_les_inscrits_d_un_autre_evenement_ne_comptent_pas(): void
    {
        $this->guests();

        $this->tenant->asCurrent(function () {
            $other = Event::factory()->open()->create();
            Registration::factory()->confirmed()->create(['event_id' => $other->id, 'unit_id' => $this->alpha->id]);
        });

        $this->list()->assertInertia(function ($page) {
            $units = collect($page->toArray()['props']['unitSummary'])->keyBy('name');

            $this->assertSame(2, $units['ALPHA TEST']['registrations']);
        });
    }

    public function test_un_tiers_n_atteint_pas_le_resume_d_une_autre_organisation(): void
    {
        $this->guests();

        $stranger = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($stranger, 'Autre Organisation');

        $this->actingAs($stranger)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event, 'filter' => ['unit' => (string) $this->alpha->id]]))
            ->assertNotFound();
    }
}
