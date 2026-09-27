<?php

namespace App\Http\Controllers\Tenants;

use App\Contracts\BillingNotConfigured;
use App\Contracts\SubscriptionBillingGateway;
use App\Enums\BillingCurrency;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\CheckoutSubscriptionRequest;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * L'ecran « Abonnement » (README ecran 16), etape 10 de « Ordre de construction » : consommation
 * des quotas, plans, moyen de paiement, factures.
 *
 * Aucune donnee de carte ne passe ici : choisir un plan ou changer de moyen de paiement envoie vers
 * une page hebergee par le fournisseur (voir `SubscriptionBillingGateway`). Cet ecran reste
 * accessible a une organisation suspendue, c'est par lui qu'elle regularise.
 */
class BillingController extends Controller
{
    /**
     * Display the plan, the quota consumption, the payment method and the invoices.
     */
    public function show(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('viewAny', [Subscription::class, $tenant]);

        $current = $tenant->plan();
        $subscription = $tenant->subscription;

        return Inertia::render('tenants/billing', [
            'tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'plan' => $this->plan($current, $current),
            'currencies' => BillingCurrency::enabledValues(),
            'defaultCurrency' => (BillingCurrency::tryFrom((string) $subscription?->currency) ?? BillingCurrency::default())->value,
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status->value,
                'statusLabel' => $subscription->status->label(),
                'paymentMethod' => $subscription->payment_method_last4 === null ? null : [
                    'brand' => $subscription->payment_method_brand,
                    'last4' => $subscription->payment_method_last4,
                ],
                'hasProviderCustomer' => $subscription->stripe_customer_id !== null,
                'currentPeriodEndsAt' => $subscription->current_period_ends_at?->toISOString(),
                'pastDueSince' => $subscription->past_due_since?->toISOString(),
                'suspendedAt' => $subscription->suspended_at?->toISOString(),
                'canceledAt' => $subscription->canceled_at?->toISOString(),
            ],
            'usage' => PlanLimits::for($tenant)->usage(),
            // PROVISOIRE : adresse par defaut tant que le proprietaire n'en a pas fourni une
            // reelle (CLAUDE.md, `config('convive.billing.sales_contact_email')`).
            'salesContactEmail' => config('convive.billing.sales_contact_email'),
            'plans' => collect(PlanCode::cases())
                ->map(fn (PlanCode $code) => $this->plan(Plan::ensure($code), $current))
                ->all(),
            'invoices' => $tenant->invoices()->orderByDesc('issued_at')->limit(24)->get()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'amount' => $invoice->amount,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status->value,
                    'statusLabel' => $invoice->status->label(),
                    'issuedAt' => $invoice->issued_at->toISOString(),
                    'hostedUrl' => $invoice->hosted_invoice_url,
                ])->all(),
        ]);
    }

    /**
     * Send the member to the provider's hosted page to subscribe to the given plan.
     *
     * Seuls les plans a prix fixe se souscrivent en ligne : le plan gratuit n'a rien a payer, le
     * plan sur devis se negocie.
     */
    public function checkout(CheckoutSubscriptionRequest $request, Tenant $tenant, string $plan, SubscriptionBillingGateway $gateway): SymfonyResponse
    {
        $code = PlanCode::tryFrom($plan);
        abort_if($code === null, 404);

        $target = Plan::ensure($code);
        $currency = $request->currency();

        // Zero (gratuit) et `null` (sur devis, ou non propose dans cette devise) ne se paient pas.
        if (! $target->priceIn($currency)) {
            return back()->withErrors(['billing' => __('billing.errors.not_purchasable')]);
        }

        try {
            return Inertia::location($gateway->checkoutUrl($tenant, $target, $currency));
        } catch (BillingNotConfigured) {
            return back()->withErrors(['billing' => __('billing.errors.not_configured')]);
        }
    }

    /**
     * Send the member to the provider's hosted page to change the payment method.
     */
    public function paymentMethod(Tenant $tenant, SubscriptionBillingGateway $gateway): SymfonyResponse
    {
        Gate::authorize('manage', [Subscription::class, $tenant]);

        if ($tenant->subscription?->stripe_customer_id === null) {
            return back()->withErrors(['billing' => __('billing.errors.no_provider_customer')]);
        }

        try {
            return Inertia::location($gateway->paymentMethodUrl($tenant));
        } catch (BillingNotConfigured) {
            return back()->withErrors(['billing' => __('billing.errors.not_configured')]);
        }
    }

    /**
     * Cancel the subscription : stops the provider's billing, keeps the date.
     */
    public function cancel(Tenant $tenant, SubscriptionBillingGateway $gateway): RedirectResponse
    {
        Gate::authorize('manage', [Subscription::class, $tenant]);

        $subscription = $tenant->subscription;

        if ($subscription === null) {
            return back()->withErrors(['billing' => __('billing.errors.no_subscription')]);
        }

        try {
            if ($subscription->stripe_subscription_id !== null) {
                $gateway->cancel($subscription);
            }
        } catch (BillingNotConfigured) {
            return back()->withErrors(['billing' => __('billing.errors.not_configured')]);
        }

        $subscription->update(['canceled_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('billing.flash.canceled')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(Plan $plan, Plan $current): array
    {
        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'prices' => collect(BillingCurrency::enabled())
                ->mapWithKeys(fn (BillingCurrency $currency) => [$currency->value => $plan->priceIn($currency)])
                ->all(),
            'maxActiveEvents' => $plan->max_active_events,
            'maxRegistrations' => $plan->max_registrations,
            'maxMembers' => $plan->max_members,
            'hasReconciliation' => $plan->has_reconciliation,
            'hasReports' => $plan->has_reports,
            'hasCustomDomain' => $plan->has_custom_domain,
            'hasSso' => $plan->has_sso,
            'current' => $plan->is($current),
        ];
    }
}
