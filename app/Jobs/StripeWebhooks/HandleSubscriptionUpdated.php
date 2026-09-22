<?php

namespace App\Jobs\StripeWebhooks;

/**
 * `customer.subscription.updated` : recale sur Stripe la fin de periode et la resiliation demandee
 * (`cancel_at_period_end`). L'organisation garde son plan jusqu'a la fin de la periode payee ; la
 * bascule vers le plan gratuit se fait a `customer.subscription.deleted` (`HandleSubscriptionEnded`).
 */
class HandleSubscriptionUpdated extends StripeSubscriptionJob
{
    public function handle(): void
    {
        $remote = $this->object();

        $subscription = $this->subscriptionFor($remote['id'] ?? null, $remote['customer'] ?? null);

        $periodEnd = $remote['current_period_end'] ?? $remote['items']['data'][0]['current_period_end'] ?? null;

        $subscription->update([
            'current_period_ends_at' => $this->date($periodEnd) ?? $subscription->current_period_ends_at,
            'canceled_at' => ($remote['cancel_at_period_end'] ?? false) ? ($subscription->canceled_at ?? now()) : null,
        ]);
    }
}
