<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\NotifyTrialDeadlines;
use App\Actions\Tenants\CreateTenant;
use App\Enums\ConsoleProfile;
use App\Enums\NotificationType;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use App\Settings\TrialSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * La periode d'essai et ses reglages (README section 3, decision du proprietaire du projet le
 * 2026-10-02) : trente jours par defaut, tout se regle depuis la console, y compris un essai sans
 * fin. A l'echeance l'organisation retombe sur le plan par defaut, sans rien perdre, et elle en est
 * prevenue avant.
 */
class TrialSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        $this->trial(enabled: true, days: 30);
    }

    private function trial(bool $enabled, ?int $days, string $plan = 'association'): void
    {
        $settings = app(TrialSettings::class);
        $settings->enabled = $enabled;
        $settings->days = $days;
        $settings->plan = $plan;
        $settings->save();
    }

    private function newTenant(?User $owner = null, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($owner ?? User::factory()->withTwoFactor()->create(), $name);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function update(User $actor, array $payload): TestResponse
    {
        return $this->actingAs($actor)->put(route('console.trial.update'), [
            'enabled' => true,
            'days' => 30,
            'plan' => 'association',
            ...$payload,
        ]);
    }

    public function test_l_essai_dure_trente_jours_par_defaut(): void
    {
        // Les valeurs de depart, celles que pose la migration des reglages.
        $this->assertSame(30, (int) config('convive.trial.days'));
    }

    public function test_une_organisation_neuve_recoit_la_duree_reglee(): void
    {
        $this->freezeSecond();

        $tenant = $this->newTenant();

        $this->assertTrue($tenant->isOnTrial());
        $this->assertTrue(now()->addDays(30)->equalTo($tenant->trial_ends_at));
        $this->assertSame('association', $tenant->plan()->code);
    }

    public function test_sans_duree_l_essai_n_a_pas_de_fin(): void
    {
        $this->trial(enabled: true, days: null);

        $tenant = $this->newTenant();

        $this->assertTrue($tenant->isOnTrial());
        $this->assertNull($tenant->trial_ends_at);
    }

    public function test_essai_coupe_une_organisation_neuve_demarre_sur_le_plan_par_defaut(): void
    {
        $this->trial(enabled: false, days: 30);

        $tenant = $this->newTenant();

        $this->assertFalse($tenant->isOnTrial());
        $this->assertNull($tenant->trial_ends_at);
        $this->assertSame(PlanCode::default()->value, $tenant->plan()->code);
    }

    public function test_le_plan_de_l_essai_se_regle(): void
    {
        $this->trial(enabled: true, days: 30, plan: 'institution');

        $this->assertSame('institution', $this->newTenant()->plan()->code);
    }

    public function test_changer_la_duree_ne_touche_pas_les_organisations_deja_ouvertes(): void
    {
        $this->freezeSecond();
        $tenant = $this->newTenant();

        $this->update($this->founder, ['days' => 7])->assertRedirect(route('console.plans'));

        $this->assertTrue(now()->addDays(30)->equalTo($tenant->fresh()->trial_ends_at));
        $this->assertTrue(now()->addDays(7)->equalTo($this->newTenant(name: 'Autre organisation')->trial_ends_at));
    }

    public function test_un_fondateur_rend_l_essai_illimite_depuis_la_console(): void
    {
        $this->update($this->founder, ['days' => null])->assertSessionHasNoErrors();

        $this->assertNull(app(TrialSettings::class)->days);
        $this->assertNull($this->newTenant()->trial_ends_at);
    }

    public function test_le_reglage_va_au_journal_central_avec_l_avant_et_l_apres(): void
    {
        $this->update($this->founder, ['days' => 14]);

        $entry = ConsoleActionLog::where('type', 'trial_settings_updated')->sole();

        $this->assertSame(30, $entry->properties['old']['days']);
        $this->assertSame(14, $entry->properties['attributes']['days']);
        $this->assertSame($this->founder->id, $entry->actor_id);
    }

    public function test_enregistrer_sans_rien_changer_n_ecrit_rien_au_journal(): void
    {
        $this->update($this->founder, []);

        $this->assertSame(0, ConsoleActionLog::where('type', 'trial_settings_updated')->count());
    }

    public function test_la_duree_et_le_plan_sont_valides(): void
    {
        $this->update($this->founder, ['days' => 0])->assertSessionHasErrors('days');
        $this->update($this->founder, ['days' => 5000])->assertSessionHasErrors('days');
        $this->update($this->founder, ['plan' => 'inconnu'])->assertSessionHasErrors('plan');

        $this->assertSame(30, app(TrialSettings::class)->days);
    }

    public function test_l_ecran_des_plans_montre_le_reglage_de_l_essai(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.plans'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('trial.enabled', true)
                ->where('trial.days', 30)
                ->where('trial.plan', 'association'),
            );
    }

    public function test_le_support_ne_regle_pas_l_essai(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->update($support, ['days' => 7])->assertForbidden();

        $this->assertSame(30, app(TrialSettings::class)->days);
    }

    public function test_un_membre_d_organisation_ne_trouve_pas_cette_route(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $this->newTenant($owner);

        $this->update($owner, ['days' => 7])->assertNotFound();
    }

    public function test_le_proprietaire_est_prevenu_sept_jours_puis_la_veille_de_la_fin(): void
    {
        Notification::fake();
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->newTenant($owner);

        // A vingt jours de la fin : rien.
        $this->travel(10)->days();
        app(NotifyTrialDeadlines::class)->handle();
        Notification::assertNothingSent();

        // A six jours : le premier rappel, une seule fois.
        $this->travel(14)->days();
        app(NotifyTrialDeadlines::class)->handle();
        app(NotifyTrialDeadlines::class)->handle();

        Notification::assertSentToTimes($owner, TenantAlert::class, 1);
        Notification::assertSentTo($owner, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::TrialEnding
            && $alert->params['count'] === 6);

        // La veille : le second.
        $this->travel(5)->days();
        $this->travel(12)->hours();
        app(NotifyTrialDeadlines::class)->handle();
        app(NotifyTrialDeadlines::class)->handle();

        Notification::assertSentToTimes($owner, TenantAlert::class, 2);
        $this->assertTrue($tenant->fresh()->isOnTrial());
    }

    public function test_le_proprietaire_est_prevenu_une_fois_quand_l_essai_a_pris_fin(): void
    {
        Notification::fake();
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->newTenant($owner);

        $this->travel(31)->days();
        app(NotifyTrialDeadlines::class)->handle();
        app(NotifyTrialDeadlines::class)->handle();

        $this->assertFalse($tenant->fresh()->isOnTrial());
        $this->assertSame(PlanCode::default()->value, $tenant->fresh()->plan()->code);
        Notification::assertSentToTimes($owner, TenantAlert::class, 1);
        Notification::assertSentTo($owner, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::TrialEnded);
    }

    public function test_un_essai_sans_fin_ou_une_organisation_abonnee_ne_recoit_aucun_rappel(): void
    {
        Notification::fake();

        $subscribed = $this->newTenant(name: 'Organisation abonnee');
        Subscription::create([
            'tenant_id' => $subscribed->id,
            'plan_id' => Plan::ensure(PlanCode::Association)->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->trial(enabled: true, days: null);
        $this->newTenant(name: 'Essai sans fin');

        $this->travel(40)->days();
        app(NotifyTrialDeadlines::class)->handle();

        Notification::assertNothingSent();
    }

    public function test_un_espace_personnel_ne_recoit_aucun_rappel_d_essai(): void
    {
        // Bogue trouve a la premiere execution : chaque compte cree recoit un espace personnel, qui
        // ne publie rien ; il recevait quand meme « votre essai se termine ».
        Notification::fake();
        $owner = User::factory()->withTwoFactor()->create();
        $personal = $owner->fresh()->personalTenant();

        $this->assertNotNull($personal);

        $this->travel(40)->days();
        app(NotifyTrialDeadlines::class)->handle();

        Notification::assertNothingSent();
    }

    public function test_prolonger_l_essai_depuis_la_console_relance_les_rappels(): void
    {
        Notification::fake();
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->newTenant($owner);

        $this->travel(25)->days();
        app(NotifyTrialDeadlines::class)->handle();
        Notification::assertSentToTimes($owner, TenantAlert::class, 1);

        $this->actingAs($this->founder)
            ->patch(route('console.organisations.trial.update', $tenant), ['ends_at' => now()->addDays(60)->toDateString()])
            ->assertSessionHasNoErrors();

        $this->travel(55)->days();
        app(NotifyTrialDeadlines::class)->handle();

        Notification::assertSentToTimes($owner, TenantAlert::class, 2);
    }

    public function test_le_back_office_dit_combien_de_jours_d_essai_il_reste(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->newTenant($owner);

        $this->travel(20)->days();

        $this->actingAs($owner)
            ->get(route('dashboard', $tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentPlan.name', 'Association')
                ->where('currentPlan.trialDaysLeft', 10),
            );
    }

    public function test_un_essai_sans_fin_n_affiche_aucun_decompte(): void
    {
        $this->trial(enabled: true, days: null);
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->newTenant($owner);

        $this->actingAs($owner)
            ->get(route('dashboard', $tenant))
            ->assertInertia(fn (Assert $page) => $page->where('currentPlan.trialDaysLeft', null));
    }
}
