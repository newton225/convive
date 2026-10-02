<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ConsoleProfile;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\Event;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Settings\ProtectionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * La limite d'exports par heure (SECURITY.md M3), reglable depuis l'ecran Securite de la console
 * (decision du proprietaire du projet, 2026-10-02) : trente par defaut.
 */
class ExportLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
    }

    /**
     * @return TestResponse<Response>
     */
    private function setLimit(User $actor, mixed $limit): TestResponse
    {
        return $this->actingAs($actor)->put(route('console.security.export-limit.update'), ['exports_per_hour' => $limit]);
    }

    public function test_la_limite_est_de_trente_par_heure_par_defaut(): void
    {
        $this->assertSame(30, AppServiceProvider::ExportsPerHour);
        $this->assertSame(30, app(ProtectionSettings::class)->exports_per_hour);
    }

    public function test_l_ecran_securite_montre_la_limite_en_cours(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.security'))
            ->assertInertia(fn (Assert $page) => $page->where('exportsPerHour', 30));
    }

    public function test_un_fondateur_regle_la_limite_et_le_journal_garde_l_avant_et_l_apres(): void
    {
        $this->setLimit($this->founder, 12)->assertRedirect(route('console.security'));

        $this->assertSame(12, app(ProtectionSettings::class)->exports_per_hour);

        $entry = ConsoleActionLog::where('type', 'export_limit_updated')->sole();

        $this->assertSame(30, $entry->properties['old']['exports_per_hour']);
        $this->assertSame(12, $entry->properties['attributes']['exports_per_hour']);
        $this->assertSame($this->founder->id, $entry->actor_id);
    }

    public function test_la_limite_reglee_est_celle_qui_s_applique_aux_exports(): void
    {
        Pdf::fake();
        $this->setLimit($this->founder, 2);

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $event = $tenant->asCurrent(fn () => Event::factory()->open()->create());
        $export = route('tenants.events.registrations.export.pdf', [$tenant, $event]);

        $this->actingAs($owner)->get($export)->assertOk();
        $this->actingAs($owner)->get($export)->assertOk();
        $this->actingAs($owner)->get($export)->assertTooManyRequests();
    }

    public function test_la_limite_ne_se_retire_pas(): void
    {
        $this->setLimit($this->founder, null)->assertSessionHasErrors('exports_per_hour');
        $this->setLimit($this->founder, 0)->assertSessionHasErrors('exports_per_hour');
        $this->setLimit($this->founder, 5000)->assertSessionHasErrors('exports_per_hour');

        $this->assertSame(30, app(ProtectionSettings::class)->exports_per_hour);
    }

    public function test_enregistrer_la_meme_valeur_n_ecrit_rien_au_journal(): void
    {
        $this->setLimit($this->founder, 30)->assertSessionHasNoErrors();

        $this->assertSame(0, ConsoleActionLog::where('type', 'export_limit_updated')->count());
    }

    public function test_le_support_ne_regle_pas_la_limite(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->setLimit($support, 500)->assertForbidden();

        $this->assertSame(30, app(ProtectionSettings::class)->exports_per_hour);
    }

    public function test_un_membre_d_organisation_ne_trouve_pas_cette_route(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->setLimit($owner, 500)->assertNotFound();
    }
}
