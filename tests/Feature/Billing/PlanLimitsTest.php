<?php

namespace Tests\Feature\Billing;

use App\Actions\Registrations\HoldRegistration;
use App\Actions\Tenants\CreateTenant;
use App\Enums\EventStatus;
use App\Enums\PlanCode;
use App\Enums\PlanFeature;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Registration;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les quotas et les options des plans (README section 3), etape 10 de « Ordre de construction » :
 * appliques cote serveur, pas seulement affiches.
 *
 * L'application des quotas est coupee dans le reste de la suite (`phpunit.xml`) : ce fichier la
 * rallume.
 */
class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.billing.enforce_plan_limits' => true]);

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    private function subscribe(PlanCode $code): void
    {
        Subscription::factory()->onPlan($code)->create(['tenant_id' => $this->tenant->id]);
        $this->tenant->unsetRelation('subscription');
    }

    private function limits(): PlanLimits
    {
        return PlanLimits::for($this->tenant->fresh());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function openEvents(int $count, array $attributes = []): void
    {
        $this->tenant->asCurrent(fn () => Event::factory()->open()->count($count)->create($attributes));
    }

    public function test_sans_abonnement_l_organisation_vit_sur_le_plan_essentiel(): void
    {
        $this->assertSame('essential', $this->tenant->fresh()->plan()->code);
        $this->assertSame(1, Plan::where('code', 'essential')->count());
    }

    public function test_le_plan_essentiel_n_autorise_qu_un_evenement_actif(): void
    {
        $this->assertTrue($this->limits()->canPublishEvent());

        $this->openEvents(1);

        $this->assertFalse($this->limits()->canPublishEvent());
    }

    public function test_un_brouillon_et_un_evenement_clos_ne_comptent_pas_comme_actifs(): void
    {
        $this->tenant->asCurrent(function () {
            Event::factory()->create(['status' => EventStatus::Draft]);
            Event::factory()->create(['status' => EventStatus::Closed]);
        });

        $this->assertSame(0, $this->limits()->activeEvents());
        $this->assertTrue($this->limits()->canPublishEvent());
    }

    public function test_le_plan_association_autorise_cinq_evenements_actifs(): void
    {
        $this->subscribe(PlanCode::Association);

        $this->openEvents(4);
        $this->assertTrue($this->limits()->canPublishEvent());

        $this->openEvents(1);
        $this->assertFalse($this->limits()->canPublishEvent());
    }

    public function test_le_plan_institution_n_a_aucun_plafond(): void
    {
        $this->subscribe(PlanCode::Institution);

        $this->openEvents(6);

        $this->assertTrue($this->limits()->canPublishEvent());
        $this->assertTrue($this->limits()->canRegister(1_000_000));
        $this->assertTrue($this->limits()->canAddMember());
    }

    public function test_publier_un_evenement_au_dela_du_plafond_est_refuse_avec_un_message(): void
    {
        $this->openEvents(1);
        $draft = $this->tenant->asCurrent(fn () => Event::factory()->create(['status' => EventStatus::Draft]));

        $this->actingAs($this->owner)
            ->post(route('tenants.events.publish', [$this->tenant, $draft]))
            ->assertSessionHasErrors('event');

        $this->assertSame(EventStatus::Draft, $this->tenant->asCurrent(fn () => $draft->fresh())->status);
    }

    public function test_le_plafond_d_inscrits_compte_les_personnes_des_dossiers_qui_occupent_une_place(): void
    {
        $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 199]);
        });

        $this->assertTrue($this->limits()->canRegister(1));
        $this->assertFalse($this->limits()->canRegister(2));
    }

    public function test_une_preuve_a_verifier_compte_parmi_les_inscrits(): void
    {
        $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            Registration::factory()->proofSubmitted()->create(['event_id' => $event->id, 'party_size' => 200]);
        });

        $this->assertFalse($this->limits()->canRegister(1));
    }

    public function test_les_dossiers_expires_annules_ou_brouillons_ne_comptent_pas(): void
    {
        $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            Registration::factory()->expired()->create(['event_id' => $event->id, 'party_size' => 100]);
            Registration::factory()->cancelled()->create(['event_id' => $event->id, 'party_size' => 100]);
            Registration::factory()->create(['event_id' => $event->id, 'status' => RegistrationStatus::Draft, 'party_size' => 100]);
        });

        $this->assertSame(0, $this->limits()->registrations());
    }

    public function test_les_inscrits_d_un_evenement_clos_ne_comptent_plus(): void
    {
        $this->tenant->asCurrent(function () {
            $event = Event::factory()->create(['status' => EventStatus::Closed]);
            Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 200]);
        });

        $this->assertTrue($this->limits()->canRegister(200));
    }

    public function test_reserver_au_dela_du_plafond_d_inscrits_est_refuse(): void
    {
        $held = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create(['tables' => [50, 10]]);
            Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 200]);
            $newcomer = Registration::factory()->create(['event_id' => $event->id, 'party_size' => 1]);

            return app(HoldRegistration::class)->handle($event, $newcomer);
        });

        $this->assertFalse($held);
    }

    public function test_reserver_sous_le_plafond_reste_possible(): void
    {
        $held = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create(['tables' => [50, 10]]);
            $registration = Registration::factory()->create(['event_id' => $event->id, 'party_size' => 1]);

            return app(HoldRegistration::class)->handle($event, $registration);
        });

        $this->assertTrue($held);
    }

    public function test_le_plafond_de_membres_compte_l_equipe_et_les_invitations_en_attente(): void
    {
        $this->assertTrue($this->limits()->canAddMember());

        $this->tenant->invitations()->create([
            'email' => 'invite@example.com',
            'profile_id' => $this->tenant->run(fn () => Profile::where('name', 'Lecture')->value('id')),
            'profile_name' => 'Lecture',
            'invited_by' => $this->owner->id,
            'expires_at' => now()->addDays(3),
        ]);

        $this->assertFalse($this->limits()->canAddMember());
    }

    public function test_inviter_au_dela_du_plafond_de_membres_est_refuse(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithProfile($this->tenant, $member, 'Lecture');
        $profileId = $this->tenant->run(fn () => Profile::where('name', 'Lecture')->value('id'));

        $this->actingAs($this->owner)
            ->post(route('tenants.invitations.store', $this->tenant), ['email' => 'nouveau@example.com', 'profile_id' => $profileId])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, $this->tenant->invitations()->count());
    }

    public function test_les_options_reservees_a_un_plan_sont_fermees_aux_autres(): void
    {
        $this->assertFalse($this->limits()->allows(PlanFeature::Reports));
        $this->assertFalse($this->limits()->allows(PlanFeature::Reconciliation));

        $this->subscribe(PlanCode::Association);

        $this->assertTrue($this->limits()->allows(PlanFeature::Reports));
        $this->assertTrue($this->limits()->allows(PlanFeature::Reconciliation));
        $this->assertFalse($this->limits()->allows(PlanFeature::Sso));
    }

    public function test_les_rapports_et_le_rapprochement_sont_refuses_sur_le_plan_essentiel(): void
    {
        $event = $this->tenant->asCurrent(fn () => Event::factory()->create());

        $this->actingAs($this->owner)->get(route('tenants.events.report.show', [$this->tenant, $event]))->assertForbidden();
        $this->actingAs($this->owner)->get(route('tenants.events.reconciliation.index', [$this->tenant, $event]))->assertForbidden();
    }

    public function test_les_rapports_et_le_rapprochement_sont_ouverts_sur_le_plan_association(): void
    {
        $this->subscribe(PlanCode::Association);
        $event = $this->tenant->asCurrent(fn () => Event::factory()->create());

        $this->actingAs($this->owner)->get(route('tenants.events.report.show', [$this->tenant, $event]))->assertOk();
        $this->actingAs($this->owner)->get(route('tenants.events.reconciliation.index', [$this->tenant, $event]))->assertOk();
    }

    public function test_l_usage_reporte_ce_qui_est_consomme_face_aux_plafonds(): void
    {
        $this->openEvents(1);

        $usage = $this->limits()->usage();

        $this->assertSame(['used' => 1, 'max' => 1], $usage['events']);
        $this->assertSame(['used' => 0, 'max' => 200], $usage['registrations']);
        $this->assertSame(['used' => 1, 'max' => 2], $usage['members']);
    }

    public function test_sans_application_des_quotas_tout_est_permis(): void
    {
        config(['convive.billing.enforce_plan_limits' => false]);

        $this->openEvents(3);

        $this->assertTrue($this->limits()->canPublishEvent());
        $this->assertTrue($this->limits()->allows(PlanFeature::Reports));
    }
}
