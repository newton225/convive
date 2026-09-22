<?php

namespace App\Jobs\StripeWebhooks;

use App\Actions\Billing\MarkSubscriptionPaid;

/**
 * `invoice.paid` : un prelevement a abouti. L'abonnement redevient a jour, la suspension est levee et
 * la facture entre a l'historique (`MarkSubscriptionPaid`, idempotente par identifiant de facture
 * Stripe : Stripe rejoue une notification tant qu'il n'a pas eu de reponse).
 *
 * Le montant reste dans la plus petite unite de la devise, tel que Stripe le donne (`amount_paid`).
 */
class HandleInvoicePaid extends StripeSubscriptionJob
{
    public function handle(MarkSubscriptionPaid $markPaid): void
    {
        $invoice = $this->object();

        $subscription = $this->subscriptionFor($invoice['subscription'] ?? null, $invoice['customer'] ?? null);

        $period = $invoice['lines']['data'][0]['period'] ?? [];

        $markPaid->handle($subscription, [
            'stripe_invoice_id' => $invoice['id'],
            'number' => $invoice['number'] ?? $invoice['id'],
            'amount' => (int) ($invoice['amount_paid'] ?? 0),
            'currency' => strtoupper((string) ($invoice['currency'] ?? 'XOF')),
            'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            'period_start' => $this->date($period['start'] ?? null),
            'period_end' => $this->date($period['end'] ?? null),
        ]);

        if (($period['end'] ?? null) !== null) {
            $subscription->update(['current_period_ends_at' => $this->date($period['end'])]);
        }
    }
}
