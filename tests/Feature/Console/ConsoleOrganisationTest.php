<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ConsoleProfile;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\Event;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Les organisations vues de la console et les actions de l'editeur (README section 3, ecrans 27
 * et 28) : des metadonnees seulement ; suspendre avec un motif et reactiver, changer de plan sans
 * depasser les quotas vises, programmer une suppression annulable trente jours. Chaque action va
 * au journal central.
 */
class ConsoleOrganisationTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    private function onPlan(PlanCode $code): void
    {
        Subscription::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['plan_id' => Plan::ensure($code)->id, 'status' => SubscriptionStatus::Active],
        );
    }

    public function test_la_liste_montre_les_vraies_organisations(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.organisations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isSample', false)
                ->where('organisations', fn ($organisations) => collect($organisations)->contains('slug', $this->tenant->slug)),
            );
    }

    public function test_la_fiche_montre_des_metadonnees_et_releve_la_consommation(): void
    {
        $this->tenant->run(fn () => Event::factory()->open()->create());

        $this->actingAs($this->founder)
            ->get(route('console.organisations.show', $this->tenant->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('console/organisation')
                ->where('organisation.slug', $this->tenant->slug)
                ->where('organisation.status', 'active')
                ->where('organisation.usage.activeEvents.used', 1)
                ->where('canAct', true),
            );
    }

    public function test_une_organisation_inconnue_recoit_404(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.organisations.show', 'inconnue'))
            ->assertNotFound();
    }

    public function test_la_suspension_exige_un_motif(): void
    {
        $this->actingAs($this->founder)
            ->post(route('console.organisations.suspend', $this->tenant), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($this->tenant->fresh()->isSuspended());
    }

    public function test_une_organisation_suspendue_par_l_editeur_voit_son_back_office_ferme(): void
    {
        $this->actingAs($this->founder)
            ->post(route('console.organisations.suspend', $this->tenant), ['reason' => 'Contenu contraire aux conditions d utilisation.'])
            ->assertRedirect(route('console.organisations.show', $this->tenant->slug));

        $this->assertTrue($this->tenant->fresh()->isSuspendedByEditor());
        $this->assertSame('tenant_suspended', ConsoleActionLog::where('tenant_id', $this->tenant->id)->sole()->type);

        $this->actingAs($this->owner)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertRedirect(route('tenants.billing.show', $this->tenant))
            ->assertSessionHasErrors(['billing' => __('billing.errors.suspended_by_editor')]);
    }

    public function test_la_reactivation_rouvre_le_back_office(): void
    {
        $this->tenant->suspension()->create(['reason' => 'Motif de la suspension.', 'suspended_by_id' => $this->founder->id]);

        $this->actingAs($this->founder)
            ->post(route('console.organisations.reactivate', $this->tenant))
            ->assertRedirect();

        $this->assertFalse($this->tenant->fresh()->isSuspended());

        $this->actingAs($this->owner)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertOk();
    }

    public function test_une_suspension_pour_impaye_ne_se_leve_pas_depuis_la_console(): void
    {
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::ensure(PlanCode::Association)->id,
            'status' => SubscriptionStatus::Suspended,
        ]);

        $this->actingAs($this->founder)
            ->post(route('console.organisations.reactivate', $this->tenant))
            ->assertSessionHasErrors('organisation');

        $this->assertTrue($this->tenant->fresh()->isSuspended());
    }

    public function test_une_montee_de_plan_prend_effet_tout_de_suite(): void
    {
        $this->actingAs($this->founder)
            ->patch(route('console.organisations.plan.update', $this->tenant), ['plan' => 'institution'])
            ->assertRedirect();

        $this->assertSame('institution', $this->tenant->fresh()->plan()->code);
        $this->assertSame('plan_changed', ConsoleActionLog::where('tenant_id', $this->tenant->id)->sole()->type);
    }

    public function test_une_descente_est_refusee_tant_que_la_consommation_depasse_les_quotas_vises(): void
    {
        $this->onPlan(PlanCode::Association);

        $essential = Plan::ensure(PlanCode::Essential);
        $essential->update(['max_active_events' => 1]);

        $this->tenant->run(fn () => Event::factory()->open()->count(2)->create());

        $this->actingAs($this->founder)
            ->patch(route('console.organisations.plan.update', $this->tenant), ['plan' => 'essential'])
            ->assertSessionHasErrors('plan');

        $this->assertSame('association', $this->tenant->fresh()->plan()->code);
    }

    public function test_une_descente_passe_quand_la_consommation_tient_dans_les_quotas(): void
    {
        $this->onPlan(PlanCode::Association);

        $this->actingAs($this->founder)
            ->patch(route('console.organisations.plan.update', $this->tenant), ['plan' => 'essential'])
            ->assertSessionHasNoErrors();

        $this->assertSame('essential', $this->tenant->fresh()->plan()->code);
    }

    public function test_un_abonnement_regle_en_ligne_ne_change_pas_de_plan_depuis_la_console(): void
    {
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => Plan::ensure(PlanCode::Association)->id,
            'status' => SubscriptionStatus::Active,
            'stripe_subscription_id' => 'sub_test',
        ]);

        $this->actingAs($this->founder)
            ->patch(route('console.organisations.plan.update', $this->tenant), ['plan' => 'institution'])
            ->assertSessionHasErrors('plan');

        $this->assertSame('association', $this->tenant->fresh()->plan()->code);
    }

    public function test_la_suppression_se_programme_a_trente_jours_et_s_annule(): void
    {
        $this->freezeTime();

        $this->actingAs($this->founder)
            ->post(route('console.organisations.deletion.schedule', $this->tenant), [
                'request_reference' => 'Email du 2026-10-01, objet « Fermeture »',
                'confirmation' => 'Association Convive',
            ])
            ->assertRedirect();

        $this->assertTrue($this->tenant->fresh()->deletion_scheduled_at->equalTo(now()->addDays(30)));

        $this->actingAs($this->founder)
            ->delete(route('console.organisations.deletion.cancel', $this->tenant))
            ->assertRedirect();

        $this->assertNull($this->tenant->fresh()->deletion_scheduled_at);
        $this->assertSame(
            ['deletion_scheduled', 'deletion_cancelled'],
            ConsoleActionLog::where('tenant_id', $this->tenant->id)->orderBy('id')->pluck('type')->all(),
        );
    }

    public function test_la_suppression_exige_la_reference_de_la_demande_et_le_nom_retape(): void
    {
        $this->actingAs($this->founder)
            ->post(route('console.organisations.deletion.schedule', $this->tenant), [
                'request_reference' => '',
                'confirmation' => 'Autre organisation',
            ])
            ->assertSessionHasErrors(['request_reference', 'confirmation']);

        $this->assertNull($this->tenant->fresh()->deletion_scheduled_at);
    }

    public function test_programmer_la_suppression_n_efface_rien(): void
    {
        $this->actingAs($this->founder)
            ->post(route('console.organisations.deletion.schedule', $this->tenant), [
                'request_reference' => 'Courrier du 2026-10-01',
                'confirmation' => 'Association Convive',
            ]);

        $this->assertNotNull(Tenant::find($this->tenant->id));
        $this->assertTrue($this->tenant->database()->manager()->databaseExists($this->tenant->database()->getName()));
    }

    public function test_le_support_lit_les_organisations_sans_pouvoir_agir(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)
            ->get(route('console.organisations.show', $this->tenant->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canAct', false));

        $this->actingAs($support)
            ->post(route('console.organisations.suspend', $this->tenant), ['reason' => 'Un motif assez long.'])
            ->assertForbidden();
        $this->actingAs($support)
            ->post(route('console.organisations.reactivate', $this->tenant))
            ->assertForbidden();
        $this->actingAs($support)
            ->patch(route('console.organisations.plan.update', $this->tenant), ['plan' => 'institution'])
            ->assertForbidden();
        $this->actingAs($support)
            ->post(route('console.organisations.deletion.schedule', $this->tenant), ['request_reference' => 'Courrier', 'confirmation' => 'Association Convive'])
            ->assertForbidden();
        $this->actingAs($support)
            ->delete(route('console.organisations.deletion.cancel', $this->tenant))
            ->assertForbidden();
    }

    public function test_un_membre_d_organisation_ne_trouve_aucune_de_ces_routes(): void
    {
        $this->actingAs($this->owner)
            ->get(route('console.organisations.show', $this->tenant->slug))
            ->assertNotFound();

        $this->actingAs($this->owner)
            ->post(route('console.organisations.suspend', $this->tenant), ['reason' => 'Un motif assez long.'])
            ->assertNotFound();
    }
}
