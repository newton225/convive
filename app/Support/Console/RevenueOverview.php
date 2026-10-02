<?php

namespace App\Support\Console;

use App\Enums\BillingCurrency;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Les revenus de l'editeur vus par la console (README section 3) : ce que les abonnements en cours
 * rapportent chaque mois, et ce qui a reellement ete encaisse. Lu sur la base centrale seule.
 *
 * Tout est rendu par devise : additionner des francs CFA, des euros et des dollars ne dirait rien.
 */
class RevenueOverview
{
    /**
     * @return array{recurring: array<int, array{currency: string, amount: int}>, subscribers: int, byPlan: array<int, array{plan: string, count: int}>, collectedThisMonth: array<int, array{currency: string, amount: int}>, collectedLastMonth: array<int, array{currency: string, amount: int}>}
     */
    public static function summary(): array
    {
        // Ce qui se renouvelle : les abonnements a jour. Un impaye ou une suspension ne compte pas
        // tant qu'il n'est pas regularise.
        $active = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereHas('tenant')
            ->with('plan')
            ->get()
            ->filter(fn (Subscription $subscription) => self::monthlyPrice($subscription) > 0);

        return [
            'recurring' => self::byCurrency($active->map(fn (Subscription $subscription) => [
                'currency' => self::currencyOf($subscription)->value,
                'amount' => self::monthlyPrice($subscription),
            ])->values()->all()),
            'subscribers' => $active->count(),
            'byPlan' => $active
                ->groupBy(fn (Subscription $subscription) => $subscription->plan->name)
                ->map(fn (Collection $subscriptions, string $plan) => ['plan' => $plan, 'count' => $subscriptions->count()])
                ->values()
                ->all(),
            'collectedThisMonth' => self::collected(now()->startOfMonth(), now()),
            'collectedLastMonth' => self::collected(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()),
        ];
    }

    /**
     * Sum the invoices paid between the two dates, by currency.
     *
     * @return array<int, array{currency: string, amount: int}>
     */
    private static function collected(CarbonInterface $from, CarbonInterface $to): array
    {
        return self::byCurrency(Invoice::query()
            ->where('status', InvoiceStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->get(['currency', 'amount'])
            ->map(fn (Invoice $invoice) => ['currency' => (string) $invoice->currency, 'amount' => (int) $invoice->amount])
            ->all());
    }

    /**
     * @param  array<int, array{currency: string, amount: int}>  $rows
     * @return array<int, array{currency: string, amount: int}>
     */
    private static function byCurrency(array $rows): array
    {
        return collect($rows)
            ->groupBy('currency')
            ->map(fn (Collection $group, string $currency) => ['currency' => $currency, 'amount' => (int) $group->sum('amount')])
            ->values()
            ->all();
    }

    private static function currencyOf(Subscription $subscription): BillingCurrency
    {
        return BillingCurrency::tryFrom((string) $subscription->currency) ?? BillingCurrency::default();
    }

    private static function monthlyPrice(Subscription $subscription): int
    {
        return (int) $subscription->plan->priceIn(self::currencyOf($subscription));
    }
}
