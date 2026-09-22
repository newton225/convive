<?php

namespace App\Contracts;

use App\Enums\BillingCurrency;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

/**
 * Le fournisseur de paiement de l'abonnement de l'organisation (README section 3), derriere une
 * interface dediee pour rester remplacable, comme `WhatsAppSender` (CLAUDE.md, « Envois »).
 *
 * Aucun encaissement des participations n'existe dans l'application : ce contrat ne concerne que
 * l'abonnement a Convive lui-meme. Le fournisseur heberge la saisie du moyen de paiement (page
 * hebergee) : aucune donnee de carte ne traverse l'application.
 */
interface SubscriptionBillingGateway
{
    /**
     * Get the address of the provider's hosted page where the tenant subscribes to the plan, billed
     * in the given currency. L'appelant a deja verifie que le plan a un prix dans cette devise.
     *
     * @throws BillingNotConfigured Quand aucun fournisseur n'est encore branche.
     */
    public function checkoutUrl(Tenant $tenant, Plan $plan, BillingCurrency $currency): string;

    /**
     * Get the address of the provider's hosted page where the tenant changes its payment method.
     *
     * @throws BillingNotConfigured
     */
    public function paymentMethodUrl(Tenant $tenant): string;

    /**
     * Cancel the subscription on the provider's side.
     *
     * @throws BillingNotConfigured
     */
    public function cancel(Subscription $subscription): void;
}
