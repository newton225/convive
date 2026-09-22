<?php

namespace App\Support\Stripe;

use Stripe\StripeClient;
use Stripe\Subscription;

/**
 * Le seul point de contact avec le SDK Stripe (`stripe/stripe-php`). Volontairement mince : chaque
 * methode est un appel, sans decision. Les choix (quoi envoyer, quoi enregistrer) vivent dans
 * `StripeSubscriptionBillingGateway` et les jobs de notification, qui se testent en remplacant
 * cette classe, sans jamais joindre Stripe.
 *
 * Aucune donnee de carte ne traverse l'application : le client saisit son moyen de paiement sur une
 * page hebergee par Stripe.
 */
class StripeApi
{
    public function __construct(private readonly StripeClient $client) {}

    /**
     * Create a Stripe customer and return its identifier.
     */
    public function createCustomer(string $name, ?string $email, int $tenantId): string
    {
        return $this->client->customers->create([
            'name' => $name,
            'email' => $email,
            // Les metadonnees Stripe sont des chaines.
            'metadata' => ['tenant_id' => (string) $tenantId],
        ])->id;
    }

    /**
     * Create a hosted checkout session and return the address to send the member to.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function createCheckoutSession(array $parameters): string
    {
        return (string) $this->client->checkout->sessions->create($parameters)->url;
    }

    /**
     * Create a hosted billing portal session (change of payment method) and return its address.
     */
    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        return $this->client->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ])->url;
    }

    /**
     * Stop the subscription at the end of the period already paid : l'organisation garde son plan
     * jusque-la, le fournisseur ne prelevera plus ensuite.
     */
    public function cancelAtPeriodEnd(string $subscriptionId): void
    {
        $this->client->subscriptions->update($subscriptionId, ['cancel_at_period_end' => true]);
    }

    /**
     * Get the subscription with its default payment method loaded.
     */
    public function retrieveSubscription(string $subscriptionId): Subscription
    {
        return $this->client->subscriptions->retrieve($subscriptionId, [
            'expand' => ['default_payment_method'],
        ]);
    }
}
