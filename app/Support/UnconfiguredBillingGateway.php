<?php

namespace App\Support;

use App\Contracts\BillingNotConfigured;
use App\Contracts\SubscriptionBillingGateway;
use App\Enums\BillingCurrency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

/**
 * Le palliatif tant qu'aucun fournisseur de paiement n'est branche : chaque operation refuse
 * explicitement, l'ecran d'abonnement le traduit en un message clair. Meme role que
 * `LogWhatsAppSender` pour WhatsApp ; le jour ou le fournisseur est installe, seule la liaison de
 * `AppServiceProvider` change.
 */
class UnconfiguredBillingGateway implements SubscriptionBillingGateway
{
    public function checkoutUrl(Tenant $tenant, Plan $plan, BillingCurrency $currency): string
    {
        throw new BillingNotConfigured;
    }

    public function paymentMethodUrl(Tenant $tenant): string
    {
        throw new BillingNotConfigured;
    }

    public function cancel(Subscription $subscription): void
    {
        throw new BillingNotConfigured;
    }
}
