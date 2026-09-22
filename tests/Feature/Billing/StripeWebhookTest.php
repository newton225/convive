<?php

namespace Tests\Feature\Billing;

use App\Actions\Tenants\CreateTenant;
use App\Enums\InvoiceStatus;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Jobs\StripeWebhooks\HandleCheckoutCompleted;
use App\Jobs\StripeWebhooks\HandleInvoicePaid;
use App\Jobs\StripeWebhooks\HandleInvoicePaymentFailed;
use App\Jobs\StripeWebhooks\HandleSubscriptionEnded;
use App\Jobs\StripeWebhooks\HandleSubscriptionUpdated;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Stripe\StripeApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Spatie\WebhookClient\Models\WebhookCall;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

/**
 * Ce que Stripe notifie et la maniere dont l'abonnement en tient compte (README section 3), etape
 * 10. Les jobs sont executes directement sur une notification construite a la main : Stripe n'est
 * jamais joint.
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association Convive');
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function notification(string $type, array $object): WebhookCall
    {
        return WebhookCall::create([
            'name' => 'stripe',
            'url' => 'https://convive.test/webhooks/stripe',
            'payload' => ['id' => 'evt_1', 'type' => $type, 'data' => ['object' => $object]],
        ]);
    }

    private function subscription(string $state = 'active'): Subscription
    {
        $factory = Subscription::factory()->onPlan(PlanCode::Association);

        $factory = match ($state) {
            'pastDue' => $factory->pastDue(4),
            'suspended' => $factory->suspended(),
            default => $factory,
        };

        return $factory->create([
            'tenant_id' => $this->tenant->id,
            'stripe_customer_id' => 'cus_123',
            'stripe_subscription_id' => 'sub_123',
        ]);
    }

    private function stripeReturning(?StripeSubscription $remote): StripeApi
    {
        $stripe = Mockery::mock(StripeApi::class);

        if ($remote === null) {
            $stripe->shouldReceive('retrieveSubscription')->andThrow(new RuntimeException('Stripe unreachable'));
        } else {
            $stripe->shouldReceive('retrieveSubscription')->andReturn($remote);
        }

        return $stripe;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutSession(array $overrides = []): array
    {
        return [
            'id' => 'cs_1',
            'customer' => 'cus_123',
            'subscription' => 'sub_123',
            'client_reference_id' => (string) $this->tenant->id,
            'metadata' => ['tenant_id' => (string) $this->tenant->id, 'plan_code' => 'association', 'currency' => 'EUR'],
            ...$overrides,
        ];
    }

    public function test_la_souscription_passe_l_organisation_au_plan_choisi(): void
    {
        $remote = StripeSubscription::constructFrom([
            'id' => 'sub_123',
            'current_period_end' => 1893456000,
            'default_payment_method' => ['card' => ['brand' => 'visa', 'last4' => '4242']],
        ]);

        (new HandleCheckoutCompleted($this->notification('checkout.session.completed', $this->checkoutSession())))->handle($this->stripeReturning($remote));

        $subscription = Subscription::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame('association', $subscription->plan->code);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame('EUR', $subscription->currency);
        $this->assertSame('cus_123', $subscription->stripe_customer_id);
        $this->assertSame('sub_123', $subscription->stripe_subscription_id);
        $this->assertSame('visa', $subscription->payment_method_brand);
        $this->assertSame('4242', $subscription->payment_method_last4);
        $this->assertSame(1893456000, $subscription->current_period_ends_at->getTimestamp());
    }

    public function test_la_souscription_est_enregistree_meme_si_stripe_ne_repond_plus(): void
    {
        (new HandleCheckoutCompleted($this->notification('checkout.session.completed', $this->checkoutSession())))->handle($this->stripeReturning(null));

        $subscription = Subscription::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame('association', $subscription->plan->code);
        $this->assertNull($subscription->payment_method_last4);
    }

    public function test_souscrire_de_nouveau_efface_l_impaye_la_suspension_et_la_resiliation(): void
    {
        $this->subscription('suspended')->update(['canceled_at' => now()->subDay()]);

        (new HandleCheckoutCompleted($this->notification('checkout.session.completed', $this->checkoutSession())))->handle($this->stripeReturning(null));

        $subscription = Subscription::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->suspended_at);
        $this->assertNull($subscription->past_due_since);
        $this->assertNull($subscription->canceled_at);
        $this->assertSame(1, Subscription::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_une_session_sans_organisation_ni_plan_identifiables_echoue_franchement(): void
    {
        $this->expectException(RuntimeException::class);

        (new HandleCheckoutCompleted($this->notification('checkout.session.completed', $this->checkoutSession(['metadata' => [], 'client_reference_id' => null]))))->handle($this->stripeReturning(null));
    }

    public function test_une_facture_reglee_remet_l_abonnement_a_jour_et_entre_a_l_historique(): void
    {
        $subscription = $this->subscription('suspended');

        $call = $this->notification('invoice.paid', [
            'id' => 'in_123',
            'number' => 'CONV-0001',
            'customer' => 'cus_123',
            'subscription' => 'sub_123',
            'amount_paid' => 3800,
            'currency' => 'eur',
            'hosted_invoice_url' => 'https://invoice.example/in_123',
            'lines' => ['data' => [['period' => ['start' => 1890777600, 'end' => 1893456000]]]],
        ]);

        app()->call([new HandleInvoicePaid($call), 'handle']);

        $fresh = $subscription->fresh();

        $this->assertSame(SubscriptionStatus::Active, $fresh->status);
        $this->assertNull($fresh->suspended_at);
        $this->assertSame(1893456000, $fresh->current_period_ends_at->getTimestamp());

        $invoice = $this->tenant->invoices()->firstOrFail();

        $this->assertSame('CONV-0001', $invoice->number);
        $this->assertSame(3800, $invoice->amount);
        $this->assertSame('EUR', $invoice->currency);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('https://invoice.example/in_123', $invoice->hosted_invoice_url);
    }

    public function test_rejouer_la_meme_facture_ne_cree_pas_de_doublon(): void
    {
        $this->subscription();
        $object = ['id' => 'in_123', 'number' => 'CONV-0001', 'customer' => 'cus_123', 'subscription' => 'sub_123', 'amount_paid' => 25000, 'currency' => 'xof'];

        app()->call([new HandleInvoicePaid($this->notification('invoice.paid', $object)), 'handle']);
        app()->call([new HandleInvoicePaid($this->notification('invoice.paid', $object)), 'handle']);

        $this->assertSame(1, $this->tenant->invoices()->count());
    }

    public function test_une_facture_arrivee_avant_la_fin_du_paiement_initial_retrouve_l_organisation_par_son_client(): void
    {
        Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'stripe_customer_id' => 'cus_123', 'stripe_subscription_id' => null]);

        app()->call([new HandleInvoicePaid($this->notification('invoice.paid', [
            'id' => 'in_1', 'number' => 'CONV-0002', 'customer' => 'cus_123', 'subscription' => 'sub_not_yet_known', 'amount_paid' => 3800, 'currency' => 'eur',
        ])), 'handle']);

        $this->assertSame(1, $this->tenant->invoices()->count());
    }

    public function test_une_facture_qui_ne_correspond_a_aucun_abonnement_echoue_franchement(): void
    {
        $this->expectException(RuntimeException::class);

        app()->call([new HandleInvoicePaid($this->notification('invoice.paid', [
            'id' => 'in_1', 'number' => 'X', 'customer' => 'cus_inconnu', 'subscription' => 'sub_inconnu', 'amount_paid' => 1, 'currency' => 'eur',
        ])), 'handle']);
    }

    public function test_un_echec_de_paiement_demarre_le_compte_a_rebours_des_impayes(): void
    {
        $subscription = $this->subscription();

        app()->call([new HandleInvoicePaymentFailed($this->notification('invoice.payment_failed', ['id' => 'in_1', 'customer' => 'cus_123', 'subscription' => 'sub_123'])), 'handle']);

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->past_due_since);
    }

    public function test_une_resiliation_demandee_est_recopiee_avec_la_fin_de_periode(): void
    {
        $subscription = $this->subscription();

        app()->call([new HandleSubscriptionUpdated($this->notification('customer.subscription.updated', [
            'id' => 'sub_123', 'customer' => 'cus_123', 'cancel_at_period_end' => true, 'current_period_end' => 1893456000,
        ])), 'handle']);

        $this->assertNotNull($subscription->fresh()->canceled_at);
        $this->assertSame(1893456000, $subscription->fresh()->current_period_ends_at->getTimestamp());
    }

    public function test_reprendre_l_abonnement_efface_la_resiliation_demandee(): void
    {
        $subscription = $this->subscription();
        $subscription->update(['canceled_at' => now()]);

        app()->call([new HandleSubscriptionUpdated($this->notification('customer.subscription.updated', [
            'id' => 'sub_123', 'customer' => 'cus_123', 'cancel_at_period_end' => false,
        ])), 'handle']);

        $this->assertNull($subscription->fresh()->canceled_at);
    }

    public function test_la_fin_de_l_abonnement_ramene_l_organisation_au_plan_gratuit(): void
    {
        $subscription = $this->subscription('pastDue');

        app()->call([new HandleSubscriptionEnded($this->notification('customer.subscription.deleted', ['id' => 'sub_123', 'customer' => 'cus_123'])), 'handle']);

        $fresh = $subscription->fresh();

        $this->assertSame('essential', $fresh->plan->code);
        $this->assertSame(SubscriptionStatus::Canceled, $fresh->status);
        $this->assertNull($fresh->stripe_subscription_id);
        $this->assertNull($fresh->past_due_since);
        $this->assertSame('essential', $this->tenant->fresh()->plan()->code);
    }

    public function test_une_notification_signee_est_acceptee_et_journalisee(): void
    {
        config(['stripe-webhooks.signing_secret' => 'whsec_test']);

        $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'ping.test', 'data' => ['object' => []]]);
        $timestamp = time();
        $signature = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test');

        $this->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], $payload)
            ->assertOk();

        $this->assertSame(1, WebhookCall::count());
    }

    public function test_une_notification_a_la_signature_invalide_est_refusee_et_ignoree(): void
    {
        config(['stripe-webhooks.signing_secret' => 'whsec_test']);

        $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'invoice.paid', 'data' => ['object' => []]]);

        $response = $this->call('POST', '/webhooks/stripe', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=falsifiee', 'CONTENT_TYPE' => 'application/json'], $payload);

        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(0, WebhookCall::count());
    }
}
