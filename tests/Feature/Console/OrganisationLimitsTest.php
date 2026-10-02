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
use App\Models\TenantLimit;
use App\Models\User;
use App\Support\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Limites propres a une organisation (decision du proprietaire du projet, 2026-10-02) : un plan
 * vendu sur devis ne donne pas les memes chiffres a tous ses clients. Une limite reglee sur la
 * fiche d'une organisation remplace celle de son plan, pour elle seule ; une limite laissee vide
 * suit le plan.
 */
class OrganisationLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'convive.console.operators' => ['fondateur@convive.test'],
            'convive.billing.enforce_plan_limits' => true,
        ]);

        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    private function onPlan(PlanCode $code, ?Tenant $tenant = null): void
    {
        Subscription::updateOrCreate(
            ['tenant_id' => ($tenant ?? $this->tenant)->id],
            ['plan_id' => Plan::ensure($code)->id, 'status' => SubscriptionStatus::Active],
        );
    }

    /**
     * @param  array<string, mixed>  $limits
     * @return TestResponse<Response>
     */
    private function setLimits(User $actor, array $limits): TestResponse
    {
        return $this->actingAs($actor)->put(route('console.organisations.limits.update', $this->tenant->slug), [
            'max_active_events' => null,
            'max_registrations' => null,
            'max_members' => null,
            'max_messages_per_month' => null,
            ...$limits,
        ]);
    }

    private function limits(): PlanLimits
    {
        return PlanLimits::for($this->tenant->fresh());
    }

    public function test_sans_limite_particuliere_l_organisation_suit_son_plan(): void
    {
        $this->onPlan(PlanCode::Association);

        $this->assertSame(5, $this->tenant->fresh()->limit('max_active_events'));
        $this->assertSame(5000, $this->limits()->usage()['messages']['max']);
    }

    public function test_une_limite_particuliere_remplace_celle_du_plan_pour_cette_organisation_seulement(): void
    {
        $this->onPlan(PlanCode::Institution);
        $other = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Autre Institution');
        $this->onPlan(PlanCode::Institution, $other);

        $this->setLimits($this->founder, ['max_messages_per_month' => 20000, 'max_members' => 40])
            ->assertRedirect();

        $usage = $this->limits()->usage();

        $this->assertSame(20000, $usage['messages']['max']);
        $this->assertSame(40, $usage['members']['max']);
        // Les limites laissees vides suivent le plan : ici, aucune.
        $this->assertNull($usage['events']['max']);
        $this->assertNull($usage['registrations']['max']);

        $this->assertNull(PlanLimits::for($other->fresh())->usage()['messages']['max']);
        $this->assertNull(Plan::ensure(PlanCode::Institution)->max_messages_per_month);
    }

    public function test_une_limite_particuliere_est_appliquee_et_pas_seulement_affichee(): void
    {
        // Essentiel : un seul evenement actif. L'organisation en obtient deux.
        $this->tenant->run(fn () => Event::factory()->open()->create());

        $this->assertFalse($this->limits()->canPublishEvent());

        $this->setLimits($this->founder, ['max_active_events' => 2]);

        $this->assertTrue($this->limits()->canPublishEvent());

        $this->tenant->run(fn () => Event::factory()->open()->create());

        $this->assertFalse($this->limits()->canPublishEvent());
    }

    public function test_une_limite_particuliere_peut_etre_plus_stricte_que_le_plan(): void
    {
        $this->onPlan(PlanCode::Association);

        $this->setLimits($this->founder, ['max_members' => 1]);

        // Le Proprietaire occupe deja la seule place.
        $this->assertFalse($this->limits()->canAddMember());
    }

    public function test_vider_une_limite_particuliere_rend_la_main_au_plan(): void
    {
        $this->setLimits($this->founder, ['max_active_events' => 9]);
        $this->assertSame(9, $this->tenant->fresh()->limit('max_active_events'));

        $this->setLimits($this->founder, []);

        $this->assertSame(1, $this->tenant->fresh()->limit('max_active_events'));
    }

    public function test_les_limites_particulieres_suivent_l_organisation_quand_elle_change_de_plan(): void
    {
        $this->setLimits($this->founder, ['max_registrations' => 3000]);

        $this->onPlan(PlanCode::Association);

        $this->assertSame(3000, $this->tenant->fresh()->limit('max_registrations'));
        $this->assertSame(5, $this->tenant->fresh()->limit('max_active_events'));
    }

    public function test_une_descente_de_plan_tient_compte_des_limites_particulieres(): void
    {
        $this->onPlan(PlanCode::Association);
        $this->tenant->run(fn () => Event::factory()->count(2)->open()->create());

        // Deux evenements actifs : Essentiel n'en accepte qu'un, la descente est refusee.
        $this->actingAs($this->founder)
            ->patch(route('console.organisations.plan.update', $this->tenant->slug), ['plan' => 'essential'])
            ->assertSessionHasErrors('plan');

        $this->setLimits($this->founder, ['max_active_events' => 3]);

        $this->actingAs($this->founder)
            ->patch(route('console.organisations.plan.update', $this->tenant->slug), ['plan' => 'essential'])
            ->assertSessionHasNoErrors();
    }

    public function test_le_changement_va_au_journal_central_avec_l_avant_et_l_apres(): void
    {
        $this->setLimits($this->founder, ['max_messages_per_month' => 20000]);
        $this->setLimits($this->founder, ['max_messages_per_month' => 50000]);

        $entry = ConsoleActionLog::where('type', 'limits_changed')->latest('id')->firstOrFail();

        $this->assertSame($this->tenant->id, $entry->tenant_id);
        $this->assertSame($this->founder->id, $entry->actor_id);
        $this->assertSame(20000, $entry->properties['old']['max_messages_per_month']);
        $this->assertSame(50000, $entry->properties['attributes']['max_messages_per_month']);
    }

    public function test_enregistrer_sans_rien_changer_n_ecrit_rien_au_journal(): void
    {
        $this->setLimits($this->founder, []);

        $this->assertSame(0, ConsoleActionLog::where('type', 'limits_changed')->count());
        $this->assertSame(0, TenantLimit::count());
    }

    public function test_une_limite_doit_etre_un_nombre_entier_positif(): void
    {
        $this->setLimits($this->founder, ['max_members' => 0])->assertSessionHasErrors('max_members');
        $this->setLimits($this->founder, ['max_messages_per_month' => 'beaucoup'])->assertSessionHasErrors('max_messages_per_month');

        $this->assertSame(0, TenantLimit::count());
    }

    public function test_la_fiche_montre_les_limites_particulieres_et_celles_du_plan(): void
    {
        $this->onPlan(PlanCode::Association);
        $this->setLimits($this->founder, ['max_members' => 40]);

        $this->actingAs($this->founder)
            ->get(route('console.organisations.show', $this->tenant->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organisation.limits.max_members.own', 40)
                ->where('organisation.limits.max_members.plan', 10)
                ->where('organisation.limits.max_messages_per_month.own', null)
                ->where('organisation.limits.max_messages_per_month.plan', 5000)
                ->where('organisation.usage.members.max', 40),
            );
    }

    public function test_le_support_lit_les_organisations_sans_pouvoir_regler_leurs_limites(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->setLimits($support, ['max_members' => 40])->assertForbidden();

        $this->assertSame(0, TenantLimit::count());
    }

    public function test_un_membre_d_organisation_ne_trouve_pas_cette_route(): void
    {
        $this->setLimits($this->owner, ['max_members' => 40])->assertNotFound();
    }
}
