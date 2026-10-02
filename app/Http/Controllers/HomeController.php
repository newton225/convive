<?php

namespace App\Http\Controllers;

use App\Enums\BillingCurrency;
use App\Enums\PlanCode;
use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La vitrine du produit (CLAUDE.md, « Animation et site produit ») : la page d'accueil du domaine
 * central, avant toute connexion.
 *
 * Les plans viennent de la table `plans`, la meme source que l'ecran d'abonnement : un prix ajuste
 * par l'exploitant se retrouve ici sans toucher au code.
 */
class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('welcome', [
            // Page commerciale : la seule famille de pages mesuree (README, « Mesure
            // d'audience »). Jamais une prop partagee, que le back-office recevrait aussi.
            'analyticsId' => config('services.google_analytics.measurement_id'),
            'plans' => collect(PlanCode::cases())
                ->map(fn (PlanCode $code) => Plan::ensure($code))
                ->map(fn (Plan $plan) => [
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'prices' => collect(BillingCurrency::enabled())
                        ->mapWithKeys(fn (BillingCurrency $currency) => [$currency->value => $plan->priceIn($currency)])
                        ->all(),
                    'maxActiveEvents' => $plan->max_active_events,
                    'maxRegistrations' => $plan->max_registrations,
                    'maxMembers' => $plan->max_members,
                    'maxMessagesPerMonth' => $plan->max_messages_per_month,
                    'hasReconciliation' => $plan->has_reconciliation,
                    'hasReports' => $plan->has_reports,
                    'hasCustomDomain' => $plan->has_custom_domain,
                    'hasSso' => $plan->has_sso,
                    'highlighted' => $plan->code === PlanCode::Association->value,
                ])
                ->all(),
            'defaultCurrency' => BillingCurrency::default()->value,
        ]);
    }
}
