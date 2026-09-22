<?php

namespace Tests\Feature\Billing;

use App\Actions\Tenants\CreateTenant;
use App\Contracts\SubscriptionBillingGateway;
use App\Enums\PlanCode;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * L'ecran « Abonnement » (README ecran 16) : consommation des quotas, plans, moyen de paiement,
 * factures. Etape 10. Le fournisseur de paiement est toujours derriere `SubscriptionBillingGateway`
 * et remplace ici par un double.
 */
class BillingControllerTest extends TestCase
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

    public function test_un_membre_avec_la_permission_voit_le_plan_l_usage_et_les_plans_proposes(): void
    {
        $this->tenant->asCurrent(fn () => Event::factory()->open()->create());

        $this->actingAs($this->owner)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tenants/billing')
                ->where('plan.code', 'essential')
                ->where('usage.events.used', 1)
                ->where('usage.events.max', 1)
                ->where('usage.registrations.max', 200)
                ->has('plans', 3)
                ->where('plans.0.code', 'essential')
                ->where('plans.0.current', true)
                ->where('subscription', null),
            );
    }

    public function test_l_ecran_montre_l_abonnement_le_moyen_de_paiement_et_les_factures(): void
    {
        $subscription = Subscription::factory()->onPlan(PlanCode::Association)->create([
            'tenant_id' => $this->tenant->id,
            'payment_method_brand' => 'visa',
            'payment_method_last4' => '4242',
        ]);
        Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'subscription_id' => $subscription->id, 'number' => 'F-000001', 'issued_at' => now()->subMonth()]);
        Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'subscription_id' => $subscription->id, 'number' => 'F-000002', 'issued_at' => now()]);

        $this->actingAs($this->owner)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertInertia(fn ($page) => $page
                ->where('plan.code', 'association')
                ->where('subscription.status', 'active')
                ->where('subscription.paymentMethod.last4', '4242')
                ->has('invoices', 2)
                ->where('invoices.0.number', 'F-000002'),
            );
    }

    public function test_les_factures_d_une_autre_organisation_ne_s_affichent_pas(): void
    {
        $other = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Autre Association');
        Invoice::factory()->create(['tenant_id' => $other->id]);

        $this->actingAs($this->owner)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertInertia(fn ($page) => $page->has('invoices', 0));
    }

    public function test_un_membre_sans_la_permission_ne_voit_pas_l_abonnement(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_l_abonnement(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertNotFound();
    }

    public function test_choisir_un_plan_payant_envoie_vers_la_page_de_paiement_du_fournisseur(): void
    {
        $this->mock(SubscriptionBillingGateway::class, function (MockInterface $gateway) {
            $gateway->shouldReceive('checkoutUrl')->once()->andReturn('https://checkout.example/session');
        });

        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'association']))
            ->assertRedirect('https://checkout.example/session');
    }

    public function test_un_plan_sur_devis_ne_se_souscrit_pas_en_ligne(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'institution']))
            ->assertSessionHasErrors('billing');
    }

    public function test_le_plan_gratuit_ne_passe_pas_par_le_paiement(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'essential']))
            ->assertSessionHasErrors('billing');
    }

    public function test_un_plan_inconnu_recoit_404(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'inexistant']))
            ->assertNotFound();
    }

    public function test_sans_fournisseur_configure_l_erreur_est_claire_et_rien_ne_plante(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'association']))
            ->assertSessionHasErrors('billing');
    }

    public function test_choisir_un_plan_exige_la_permission_de_gestion(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::BillingView]);

        $this->actingAs($member)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'association']))
            ->assertForbidden();
    }

    public function test_changer_de_moyen_de_paiement_envoie_vers_le_portail_du_fournisseur(): void
    {
        Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'stripe_customer_id' => 'cus_123']);

        $this->mock(SubscriptionBillingGateway::class, function (MockInterface $gateway) {
            $gateway->shouldReceive('paymentMethodUrl')->once()->andReturn('https://portal.example/session');
        });

        $this->actingAs($this->owner)
            ->post(route('tenants.billing.payment-method', $this->tenant))
            ->assertRedirect('https://portal.example/session');
    }

    public function test_resilier_previent_le_fournisseur_et_marque_la_date(): void
    {
        $subscription = Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'stripe_subscription_id' => 'sub_123']);

        $this->mock(SubscriptionBillingGateway::class, function (MockInterface $gateway) {
            $gateway->shouldReceive('cancel')->once();
        });

        $this->actingAs($this->owner)
            ->post(route('tenants.billing.cancel', $this->tenant))
            ->assertRedirect();

        $this->assertNotNull($subscription->fresh()->canceled_at);
    }

    public function test_resilier_sans_abonnement_est_refuse(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.billing.cancel', $this->tenant))
            ->assertSessionHasErrors('billing');
    }

    public function test_resilier_exige_la_permission_de_gestion(): void
    {
        Subscription::factory()->create(['tenant_id' => $this->tenant->id]);
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::BillingView]);

        $this->actingAs($member)
            ->post(route('tenants.billing.cancel', $this->tenant))
            ->assertForbidden();
    }
}
