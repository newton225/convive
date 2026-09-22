<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre un reglement (README section 3) : l'abonnement redevient a jour, la suspension est
 * levee, la facture est inscrite a l'historique. Appele par la notification du fournisseur de
 * paiement.
 *
 * Idempotent : la facture est retrouvee par `stripe_invoice_id`, un rejeu du meme evenement ne cree
 * pas de doublon.
 */
class MarkSubscriptionPaid
{
    /**
     * @param  array{stripe_invoice_id?: string, number: string, amount: int, currency?: string, hosted_invoice_url?: string|null, period_start?: mixed, period_end?: mixed}|null  $invoice
     */
    public function handle(Subscription $subscription, ?array $invoice = null): Subscription
    {
        DB::transaction(function () use ($subscription, $invoice) {
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'past_due_since' => null,
                'overdue_reminder_sent_at' => null,
                'suspended_at' => null,
            ]);

            if ($invoice === null) {
                return;
            }

            $attributes = [
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'number' => $invoice['number'],
                'amount' => $invoice['amount'],
                'currency' => $invoice['currency'] ?? 'XOF',
                'status' => InvoiceStatus::Paid,
                'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
                'period_start' => $invoice['period_start'] ?? null,
                'period_end' => $invoice['period_end'] ?? null,
                'paid_at' => now(),
            ];

            if (isset($invoice['stripe_invoice_id'])) {
                Invoice::updateOrCreate(
                    ['stripe_invoice_id' => $invoice['stripe_invoice_id']],
                    [...$attributes, 'issued_at' => now()],
                );

                return;
            }

            Invoice::create([...$attributes, 'issued_at' => now()]);
        });

        return $subscription;
    }
}
