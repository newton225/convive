<?php

namespace Tests\Feature\Billing;

use App\Actions\Tenants\CreateTenant;
use App\Contracts\SubscriptionBillingGateway;
use App\Enums\BillingCurrency;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Stripe\StripeApi;
use App\Support\Stripe\StripeSubscriptionBillingGateway;
use App\Support\UnconfiguredBillingGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * L'adaptateur Stripe de l'abonnement (README section 3), etape 10. Stripe n'est jamais joint :
 * `StripeApi`, la seule classe qui touche le SDK, est remplacee par un double, et l'on verifie ce
 * que l'adaptateur decide d'envoyer.
 */
class StripeGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create(['email' => 'responsable@example.com']);
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    /**
     * @param  callable(MockInterface): void  $expectations
     */
    private function gateway(callable $expectations): StripeSubscriptionBillingGateway
    {
        $stripe = Mockery::mock(StripeApi::class);
        $expectations($stripe);

        return new StripeSubscriptionBillingGateway($stripe);
    }

    public function test_la_page_de_paiement_porte_le_prix_la_devise_et_les_metadonnees_du_plan(): void
    {
        $plan = Plan::ensure(PlanCode::Association);

        $gateway = $this->gateway(function (MockInterface $stripe) {
            $stripe->shouldReceive('createCustomer')->once()->andReturn('cus_123');
            $stripe->shouldReceive('createCheckoutSession')->once()->with(Mockery::on(function (array $session) {
                $line = $session['line_items'][0];

                return $session['mode'] === 'subscription'
                    && $session['customer'] === 'cus_123'
                    && $session['client_reference_id'] === (string) $this->tenant->id
                    && $line['quantity'] === 1
                    && $line['price_data']['currency'] === 'eur'
                    && $line['price_data']['unit_amount'] === 3800
                    && $line['price_data']['recurring'] === ['interval' => 'month']
                    && $session['metadata'] === ['tenant_id' => (string) $this->tenant->id, 'plan_code' => 'association', 'currency' => 'EUR']
                    && $session['subscription_data']['metadata'] === $session['metadata']
                    && str_contains($session['success_url'], '/billing');
            }))->andReturn('https://checkout.example/session');
        });

        $this->assertSame('https://checkout.example/session', $gateway->checkoutUrl($this->tenant, $plan, BillingCurrency::Eur));
    }

    public function test_le_franc_cfa_s_envoie_sans_decimale(): void
    {
        $plan = Plan::ensure(PlanCode::Association);

        $gateway = $this->gateway(function (MockInterface $stripe) {
            $stripe->shouldReceive('createCustomer')->andReturn('cus_123');
            $stripe->shouldReceive('createCheckoutSession')->once()->with(Mockery::on(fn (array $session) => $session['line_items'][0]['price_data']['currency'] === 'xof'
                && $session['line_items'][0]['price_data']['unit_amount'] === 25000))->andReturn('https://checkout.example/session');
        });

        $gateway->checkoutUrl($this->tenant, $plan, BillingCurrency::Xof);
    }

    public function test_le_dollar_s_envoie_en_centimes(): void
    {
        $plan = Plan::ensure(PlanCode::Association);

        $gateway = $this->gateway(function (MockInterface $stripe) {
            $stripe->shouldReceive('createCustomer')->andReturn('cus_123');
            $stripe->shouldReceive('createCheckoutSession')->once()->with(Mockery::on(fn (array $session) => $session['line_items'][0]['price_data']['currency'] === 'usd'
                && $session['line_items'][0]['price_data']['unit_amount'] === 4200))->andReturn('https://checkout.example/session');
        });

        $gateway->checkoutUrl($this->tenant, $plan, BillingCurrency::Usd);
    }

    public function test_le_client_est_cree_une_fois_avec_le_responsable_puis_retenu(): void
    {
        $plan = Plan::ensure(PlanCode::Association);

        $gateway = $this->gateway(function (MockInterface $stripe) {
            $stripe->shouldReceive('createCustomer')
                ->once()
                ->with('Association Convive', 'responsable@example.com', $this->tenant->id)
                ->andReturn('cus_123');
            $stripe->shouldReceive('createCheckoutSession')->twice()->andReturn('https://checkout.example/session');
        });

        $gateway->checkoutUrl($this->tenant, $plan, BillingCurrency::Eur);
        $gateway->checkoutUrl($this->tenant->fresh(), $plan, BillingCurrency::Eur);

        $subscription = Subscription::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame('cus_123', $subscription->stripe_customer_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(PlanCode::Essential->value, $subscription->plan->code);
    }

    public function test_un_client_deja_connu_n_est_pas_recree_et_le_plan_courant_est_conserve(): void
    {
        Subscription::factory()->onPlan(PlanCode::Association)->create(['tenant_id' => $this->tenant->id, 'stripe_customer_id' => 'cus_existing']);

        $gateway = $this->gateway(function (MockInterface $stripe) {
            $stripe->shouldNotReceive('createCustomer');
            $stripe->shouldReceive('createCheckoutSession')->once()->with(Mockery::on(fn (array $session) => $session['customer'] === 'cus_existing'))->andReturn('https://checkout.example/session');
        });

        $gateway->checkoutUrl($this->tenant->fresh(), Plan::ensure(PlanCode::Association), BillingCurrency::Eur);

        $this->assertSame('association', $this->tenant->fresh()->plan()->code);
    }

    public function test_le_portail_de_paiement_revient_sur_l_ecran_d_abonnement(): void
    {
        Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'stripe_customer_id' => 'cus_123']);

        $gateway = $this->gateway(function (MockInterface $stripe) {
            $stripe->shouldReceive('createPortalSession')
                ->once()
                ->with('cus_123', Mockery::on(fn (string $url) => str_contains($url, '/billing')))
                ->andReturn('https://portal.example/session');
        });

        $this->assertSame('https://portal.example/session', $gateway->paymentMethodUrl($this->tenant->fresh()));
    }

    public function test_la_resiliation_s_arrete_a_la_fin_de_la_periode_payee(): void
    {
        $subscription = Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'stripe_subscription_id' => 'sub_123']);

        $gateway = $this->gateway(function (MockInterface $stripe) {
            $stripe->shouldReceive('cancelAtPeriodEnd')->once()->with('sub_123');
        });

        $gateway->cancel($subscription);
    }

    public function test_sans_cle_stripe_l_application_refuse_les_operations_de_paiement(): void
    {
        config(['services.stripe.secret' => null]);

        $this->assertInstanceOf(UnconfiguredBillingGateway::class, app(SubscriptionBillingGateway::class));
    }

    public function test_avec_une_cle_stripe_l_application_utilise_l_adaptateur_stripe(): void
    {
        config(['services.stripe.secret' => 'sk_test_123']);

        $this->assertInstanceOf(StripeSubscriptionBillingGateway::class, app(SubscriptionBillingGateway::class));
    }

    public function test_le_prix_d_un_plan_se_lit_dans_chaque_devise_proposee(): void
    {
        $plan = Plan::ensure(PlanCode::Association);

        $this->assertSame(25000, $plan->priceIn(BillingCurrency::Xof));
        $this->assertSame(3800, $plan->priceIn(BillingCurrency::Eur));
        $this->assertSame(4200, $plan->priceIn(BillingCurrency::Usd));
        $this->assertNull(Plan::ensure(PlanCode::Institution)->priceIn(BillingCurrency::Eur));
        $this->assertSame(0, Plan::ensure(PlanCode::Essential)->priceIn(BillingCurrency::Usd));
    }

    public function test_les_devises_proposees_suivent_la_configuration(): void
    {
        config(['convive.billing.currencies' => ['EUR', 'usd', 'inconnue']]);

        $this->assertSame(['EUR', 'USD'], BillingCurrency::enabledValues());
        $this->assertSame(BillingCurrency::Eur, BillingCurrency::default());
    }

    public function test_une_configuration_vide_retombe_sur_le_franc_cfa(): void
    {
        config(['convive.billing.currencies' => []]);

        $this->assertSame([BillingCurrency::Xof], BillingCurrency::enabled());
    }
}
