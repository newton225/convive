<?php

namespace App\Support\Stripe;

use App\Contracts\SubscriptionBillingGateway;
use App\Enums\BillingCurrency;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

/**
 * L'abonnement des organisations facture par Stripe (README section 3). Stripe heberge la saisie du
 * moyen de paiement (Checkout) et son changement (portail client) : cette classe ne fait que
 * preparer ces deux pages et retenir l'identifiant du client. Ce que Stripe repond ensuite (paiement
 * reussi, echec, fin d'abonnement) revient par les jobs de `App\Jobs\StripeWebhooks`.
 *
 * Le prix est envoye en ligne (`price_data`), sans catalogue a maintenir dans le tableau de bord
 * Stripe : la source des prix reste la table `plans`.
 */
class StripeSubscriptionBillingGateway implements SubscriptionBillingGateway
{
    public function __construct(private readonly StripeApi $stripe) {}

    public function checkoutUrl(Tenant $tenant, Plan $plan, BillingCurrency $currency): string
    {
        $customerId = $this->customerFor($tenant);

        // Les memes valeurs sur la session et sur l'abonnement : la notification `checkout.session.
        // completed` lit la session, les suivantes lisent l'abonnement.
        $metadata = [
            'tenant_id' => (string) $tenant->id,
            'plan_code' => $plan->code,
            'currency' => $currency->value,
        ];

        return $this->stripe->createCheckoutSession([
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $tenant->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency->stripeCode(),
                    'unit_amount' => $plan->priceIn($currency),
                    'recurring' => ['interval' => 'month'],
                    'product_data' => ['name' => config('app.name').' - '.$plan->name],
                ],
            ]],
            'metadata' => $metadata,
            'subscription_data' => ['metadata' => $metadata],
            'success_url' => route('tenants.billing.show', $tenant),
            'cancel_url' => route('tenants.billing.show', $tenant),
        ]);
    }

    public function paymentMethodUrl(Tenant $tenant): string
    {
        return $this->stripe->createPortalSession(
            $this->customerFor($tenant),
            route('tenants.billing.show', $tenant),
        );
    }

    public function cancel(Subscription $subscription): void
    {
        $this->stripe->cancelAtPeriodEnd((string) $subscription->stripe_subscription_id);
    }

    /**
     * Get the tenant's Stripe customer, creating and remembering it on first use.
     *
     * Retenu des la creation de la page de paiement, pas a son retour : les notifications de Stripe
     * peuvent arriver dans le desordre, et une facture reglee doit toujours retrouver son
     * organisation par ce client, meme si `checkout.session.completed` n'est pas encore passee.
     */
    private function customerFor(Tenant $tenant): string
    {
        $subscription = $tenant->subscription;

        if ($subscription?->stripe_customer_id !== null) {
            return $subscription->stripe_customer_id;
        }

        $customerId = $this->stripe->createCustomer($tenant->name, $tenant->owner()?->email, $tenant->id);

        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $subscription->plan_id ?? $tenant->plan()->id,
                'status' => $subscription->status ?? SubscriptionStatus::Active,
                'stripe_customer_id' => $customerId,
            ],
        );

        $tenant->unsetRelation('subscription');

        return $customerId;
    }
}
