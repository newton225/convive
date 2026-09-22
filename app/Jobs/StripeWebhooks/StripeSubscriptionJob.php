<?php

namespace App\Jobs\StripeWebhooks;

use App\Models\Subscription;
use Illuminate\Support\Carbon;
use RuntimeException;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

/**
 * Socle des jobs qui appliquent a un abonnement ce que Stripe notifie (`spatie/laravel-stripe-webhooks`,
 * voir `config/stripe-webhooks.php`). Tout s'execute sur la base centrale, sans organisation active.
 *
 * Stripe ne garantit pas l'ordre des notifications : une facture reglee peut arriver avant la fin du
 * paiement initial. L'abonnement se retrouve donc par son identifiant Stripe, puis a defaut par le
 * client, retenu des la creation de la page de paiement (`StripeSubscriptionBillingGateway`).
 * Introuvable : le job echoue franchement plutot que d'ignorer un paiement en silence.
 */
abstract class StripeSubscriptionJob extends ProcessWebhookJob
{
    /**
     * Get the Stripe object the notification is about (`data.object`).
     *
     * @return array<string, mixed>
     */
    protected function object(): array
    {
        return $this->webhookCall->payload['data']['object'] ?? [];
    }

    protected function subscriptionFor(?string $stripeSubscriptionId, ?string $stripeCustomerId): Subscription
    {
        $subscription = ($stripeSubscriptionId ? Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first() : null)
            ?? ($stripeCustomerId ? Subscription::where('stripe_customer_id', $stripeCustomerId)->first() : null);

        return $subscription ?? throw new RuntimeException(
            "No subscription matches the Stripe notification (subscription [{$stripeSubscriptionId}], customer [{$stripeCustomerId}]).",
        );
    }

    /**
     * Convert a Stripe timestamp, or null when absent.
     */
    protected function date(mixed $timestamp): ?Carbon
    {
        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}
