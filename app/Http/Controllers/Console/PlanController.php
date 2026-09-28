<?php

namespace App\Http\Controllers\Console;

use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le catalogue des plans (README ecran 30). Contrairement aux autres ecrans de la console, il lit
 * les vraies lignes `plans` de la base centrale : elles existent deja (etape 10) et ne portent que
 * des prix et des quotas, rien de propre a une organisation. PROVISOIRE : la modification arrive
 * avec l'etape 10, l'ecran reste en lecture seule d'ici la.
 */
class PlanController extends Controller
{
    public function __invoke(): Response
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
                    'hasReconciliation' => $plan->has_reconciliation,
                    'hasReports' => $plan->has_reports,
                ])
                ->all(),
        ]);
    }
}
