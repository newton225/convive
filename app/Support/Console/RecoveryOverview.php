<?php

namespace App\Support\Console;

use App\Actions\Billing\ProcessOverdueSubscriptions;
use App\Enums\BillingCurrency;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\ConsoleActionLog;
use App\Models\Invoice;
use App\Models\Subscription;

/**
 * Le recouvrement vu de la console (README ecran 29) : impayes en cours, relances envoyees,
 * suspensions a venir a J+10, paiements en echec. Tout vient de la base centrale.
 */
class RecoveryOverview
{
    /**
     * Nombre de paiements en echec relus a l'ecran.
     */
    private const FailedPaymentsShown = 50;

    /**
     * Get the organisations whose subscription is overdue or suspended for non-payment.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function unpaid(): array
    {
        return Subscription::query()
            ->whereIn('status', [SubscriptionStatus::PastDue, SubscriptionStatus::Suspended])
            // Une organisation mise a la corbeille n'a plus rien a recouvrer.
            ->whereHas('tenant')
            ->with('tenant', 'plan')
            ->orderBy('past_due_since')
            ->get()
            ->map(function (Subscription $subscription) {
                $currency = BillingCurrency::tryFrom((string) $subscription->currency) ?? BillingCurrency::default();

                // Ce qui reste a regler : les factures ouvertes ou en echec, a defaut une mensualite.
                $invoiced = (int) Invoice::where('subscription_id', $subscription->id)
                    ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Failed])
                    ->sum('amount');

                return [
                    'slug' => $subscription->tenant->slug,
                    'name' => $subscription->tenant->name,
                    'planName' => $subscription->plan->name,
                    'amount' => $invoiced > 0 ? $invoiced : (int) $subscription->plan->priceIn($currency),
                    'currency' => $currency->value,
                    'status' => $subscription->status === SubscriptionStatus::Suspended ? 'suspended' : 'past_due',
                    'pastDueSince' => $subscription->past_due_since?->toISOString(),
                    'remindersSent' => self::remindersSent($subscription),
                    'suspendsAt' => $subscription->status === SubscriptionStatus::PastDue
                        ? $subscription->past_due_since?->addDays(ProcessOverdueSubscriptions::SuspensionAfterDays)->toISOString()
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get the latest failed payments.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function failedPayments(): array
    {
        return Invoice::query()
            ->where('status', InvoiceStatus::Failed)
            ->with('tenant')
            ->latest('issued_at')
            ->limit(self::FailedPaymentsShown)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'name' => $invoice->tenant->name,
                'slug' => $invoice->tenant->slug,
                'at' => $invoice->issued_at->toISOString(),
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
            ])
            ->all();
    }

    /**
     * Sum what is owed, per currency : un total qui melangerait des francs et des euros ne dirait
     * rien.
     *
     * @param  array<int, array<string, mixed>>  $unpaid
     * @return array<int, array{currency: string, amount: int}>
     */
    public static function amountsDue(array $unpaid): array
    {
        return collect($unpaid)
            ->groupBy('currency')
            ->map(fn ($rows, string $currency) => ['currency' => $currency, 'amount' => (int) $rows->sum('amount')])
            ->values()
            ->all();
    }

    /**
     * Count the reminders sent since the subscription went overdue : l'automatique de J+3, plus
     * celles envoyees a la main depuis la console.
     */
    private static function remindersSent(Subscription $subscription): int
    {
        return ($subscription->overdue_reminder_sent_at !== null ? 1 : 0)
            + ConsoleActionLog::where('type', 'payment_reminder_sent')
                ->where('tenant_id', $subscription->tenant_id)
                ->when($subscription->past_due_since, fn ($query, $since) => $query->where('created_at', '>=', $since))
                ->count();
    }
}
