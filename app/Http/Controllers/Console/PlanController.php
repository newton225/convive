<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\UpdatePlan;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le catalogue des plans (README ecran 30) : les vraies lignes `plans` de la base centrale, leurs
 * prix et leurs quotas, que la console regle. Le code, le nom et l'ordre d'un plan restent dans le
 * code (`PlanCode`).
 */
class PlanController extends Controller
{
    /**
     * Display the plans.
     */
    public function index(): Response
    {
        return Inertia::render('console/plans', [
            'isSample' => false,
            'plans' => collect(PlanCode::cases())
                ->map(fn (PlanCode $code) => Plan::ensure($code))
                ->map(fn (Plan $plan) => [
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'monthlyPrice' => $plan->monthly_price,
                    'monthlyPriceEur' => $plan->monthly_price_eur,
                    'monthlyPriceUsd' => $plan->monthly_price_usd,
                    'maxActiveEvents' => $plan->max_active_events,
                    'maxRegistrations' => $plan->max_registrations,
                    'maxMembers' => $plan->max_members,
                    'maxMessagesPerMonth' => $plan->max_messages_per_month,
                    'hasReconciliation' => $plan->has_reconciliation,
                    'hasReports' => $plan->has_reports,
                ])
                ->all(),
        ]);
    }

    /**
     * Update the prices and quotas of the given plan.
     */
    public function update(UpdatePlanRequest $request, string $plan, UpdatePlan $update): RedirectResponse
    {
        // Le code arrive par l'URL : il vient du catalogue, jamais d'une chaine libre.
        $code = PlanCode::tryFrom($plan);
        abort_if($code === null, 404);

        $updated = $update->handle(Plan::ensure($code), $request->attributesForPlan(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.plans.flash.updated', ['plan' => $updated->name])]);

        return to_route('console.plans');
    }
}
