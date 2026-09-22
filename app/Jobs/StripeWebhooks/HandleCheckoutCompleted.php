<?php

namespace App\Jobs\StripeWebhooks;

use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Stripe\StripeApi;
use RuntimeException;

/**
 * `checkout.session.completed` : l'organisation vient de souscrire. Le plan et la devise choisis
 * reviennent dans les metadonnees posees par `StripeSubscriptionBillingGateway::checkoutUrl()`.
 *
 * L'abonnement passe au plan choisi. Le moyen de paiement (marque, quatre derniers chiffres) est lu
 * ensuite chez Stripe : un echec de cette lecture n'annule pas la souscription, il laisse seulement
 * l'affichage du moyen de paiement vide jusqu'a la prochaine notification.
 */
class HandleCheckoutCompleted extends StripeSubscriptionJob
{
    public function handle(StripeApi $stripe): void
    {
        $session = $this->object();
        $metadata = $session['metadata'] ?? [];

        $tenantId = $metadata['tenant_id'] ?? $session['client_reference_id'] ?? null;
        $tenant = is_numeric($tenantId) ? Tenant::find((int) $tenantId) : null;
        $code = PlanCode::tryFrom((string) ($metadata['plan_code'] ?? ''));

        if ($tenant === null || $code === null) {
            throw new RuntimeException('The checkout session does not identify a tenant and a plan.');
        }

        $subscription = Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => Plan::ensure($code)->id,
                'status' => SubscriptionStatus::Active,
                'currency' => $metadata['currency'] ?? null,
                'stripe_customer_id' => $session['customer'] ?? null,
                'stripe_subscription_id' => $session['subscription'] ?? null,
                'past_due_since' => null,
                'overdue_reminder_sent_at' => null,
                'suspended_at' => null,
                'canceled_at' => null,
            ],
        );

        $this->syncPaymentMethod($stripe, $subscription);
    }

    private function syncPaymentMethod(StripeApi $stripe, Subscription $subscription): void
    {
        if ($subscription->stripe_subscription_id === null) {
            return;
        }

        try {
            $remote = $stripe->retrieveSubscription($subscription->stripe_subscription_id);
        } catch (\Throwable) {
            return;
        }

        $card = $remote->default_payment_method->card ?? null;

        $subscription->update([
            'payment_method_brand' => $card?->brand,
            'payment_method_last4' => $card?->last4,
            'current_period_ends_at' => $this->date($remote->current_period_end ?? $remote->items->data[0]->current_period_end ?? null),
        ]);
    }
}
