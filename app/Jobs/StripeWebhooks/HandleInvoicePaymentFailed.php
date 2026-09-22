<?php

namespace App\Jobs\StripeWebhooks;

use App\Actions\Billing\MarkSubscriptionPastDue;

/**
 * `invoice.payment_failed` : un prelevement a echoue. Demarre le compte a rebours de la relance (J+3)
 * et de la suspension (J+10) de `ProcessOverdueSubscriptions`.
 */
class HandleInvoicePaymentFailed extends StripeSubscriptionJob
{
    public function handle(MarkSubscriptionPastDue $markPastDue): void
    {
        $invoice = $this->object();

        $markPastDue->handle($this->subscriptionFor($invoice['subscription'] ?? null, $invoice['customer'] ?? null));
    }
}
