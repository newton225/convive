<?php

namespace App\Jobs\StripeWebhooks;

use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;

/**
 * `customer.subscription.deleted` : l'abonnement est termine (resiliation arrivee a echeance, ou
 * abandon apres les relances de Stripe). L'organisation retombe sur le plan par defaut : ses
 * quotas redeviennent ceux d'Essentiel, ses donnees ne sont pas touchees.
 */
class HandleSubscriptionEnded extends StripeSubscriptionJob
{
    public function handle(): void
    {
        $remote = $this->object();

        $subscription = $this->subscriptionFor($remote['id'] ?? null, $remote['customer'] ?? null);

        $subscription->update([
            'plan_id' => Plan::ensure(PlanCode::default())->id,
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => $subscription->canceled_at ?? now(),
            'stripe_subscription_id' => null,
            'past_due_since' => null,
            'overdue_reminder_sent_at' => null,
            'suspended_at' => null,
            'current_period_ends_at' => null,
        ]);
    }
}
