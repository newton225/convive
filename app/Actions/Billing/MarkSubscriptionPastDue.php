<?php

namespace App\Actions\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;

/**
 * Enregistre un echec de prelevement (README section 3) : demarre le compte a rebours de la
 * relance (J+3) et de la suspension (J+10). Appele par la notification du fournisseur de paiement.
 *
 * Seul un abonnement a jour bascule : un second echec ne repousse jamais `past_due_since`, sinon un
 * prelevement qui echoue chaque jour ne serait jamais suspendu, et un espace deja suspendu ne
 * redevient pas simplement « impaye ».
 */
class MarkSubscriptionPastDue
{
    public function handle(Subscription $subscription): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            return $subscription;
        }

        $subscription->update([
            'status' => SubscriptionStatus::PastDue,
            'past_due_since' => now(),
        ]);

        return $subscription;
    }
}
