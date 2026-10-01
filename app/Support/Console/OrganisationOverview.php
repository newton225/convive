<?php

namespace App\Support\Console;

use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\ConsoleActionLog;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;

/**
 * Ce que la console montre d'une organisation (README section 3, ecrans 27 et 28) : des
 * metadonnees, jamais son contenu. Tout vient de la base centrale ; la consommation, des compteurs
 * que `TenantUsageRecorder` y range.
 */
class OrganisationOverview
{
    /**
     * Nombre d'actions de l'editeur relues sur la fiche.
     */
    private const ConsoleActionsShown = 20;

    private static ?Plan $defaultPlan = null;

    private static ?Plan $trialPlan = null;

    /**
     * Get the line of the organisations list. Expects `subscription.plan`, `usage` and
     * `suspension` to be loaded when called for a whole list.
     *
     * @return array<string, mixed>
     */
    public static function summary(Tenant $tenant): array
    {
        $subscription = $tenant->subscription;
        // Le plan par defaut et celui de l'essai ne sont lus qu'une fois pour toute la liste.
        $plan = $subscription->plan ?? ($tenant->isOnTrial()
            ? (self::$trialPlan ??= Plan::ensure(Tenant::trialPlanCode()))
            : (self::$defaultPlan ??= Plan::ensure(PlanCode::default())));
        $usage = $tenant->usage;

        return [
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'plan' => $plan->code,
            'planName' => $plan->name,
            'status' => self::status($tenant),
            'openedAt' => $tenant->created_at?->toISOString(),
            'lastActivityAt' => $usage?->last_activity_at?->toISOString(),
            'usage' => [
                'activeEvents' => ['used' => $usage->active_events ?? 0, 'max' => $plan->max_active_events],
                'registrations' => ['used' => $usage->registrations ?? 0, 'max' => $plan->max_registrations],
                'members' => ['used' => $usage->members ?? 0, 'max' => $plan->max_members],
            ],
            'pastDueSince' => $subscription?->past_due_since?->toISOString(),
            'suspendedAt' => ($tenant->suspension->created_at ?? $subscription?->suspended_at)?->toISOString(),
            // A l'essai, avec ou sans date de fin (nulle : sans fin).
            'onTrial' => $tenant->isOnTrial(),
            'trialEndsAt' => $tenant->isOnTrial() ? $tenant->trial_ends_at?->toISOString() : null,
            'deletionAt' => $tenant->deletion_scheduled_at?->toISOString(),
        ];
    }

    /**
     * Get the sheet of one organisation.
     *
     * @return array<string, mixed>
     */
    public static function details(Tenant $tenant): array
    {
        $branding = $tenant->branding;

        $actions = ConsoleActionLog::where('tenant_id', $tenant->id)
            ->latest('created_at')
            ->latest('id')
            ->limit(self::ConsoleActionsShown)
            ->get();

        $supportAccess = SupportAccessGrant::where('tenant_id', $tenant->id)
            ->active()
            ->with('operator', 'grantedBy')
            ->latest('id')
            ->first();

        return [
            ...self::summary($tenant),
            'legalName' => $branding->legal_name ?? $tenant->name,
            'contactEmail' => $branding?->email,
            'subdomain' => $tenant->subdomain,
            'suspensionReason' => $tenant->suspension?->reason,
            // Vrai quand la suspension vient de l'editeur : c'est la seule qu'il leve d'ici.
            'suspendedByEditor' => $tenant->isSuspendedByEditor(),
            // Un abonnement l'emporte sur l'essai : on n'en offre pas a une organisation abonnee.
            'hasSubscription' => $tenant->subscription !== null,
            'history' => [
                [
                    'at' => $tenant->created_at?->toISOString(),
                    'type' => 'opened',
                    'detail' => null,
                ],
                ...ConsoleActionLog::where('tenant_id', $tenant->id)
                    ->where('type', 'plan_changed')
                    ->oldest('created_at')
                    ->get()
                    ->map(fn (ConsoleActionLog $entry) => [
                        'at' => $entry->created_at->toISOString(),
                        'type' => 'plan_changed',
                        'detail' => $entry->properties['detail'] ?? null,
                    ])
                    ->all(),
            ],
            'invoices' => $tenant->invoices()
                ->latest('issued_at')
                ->limit(24)
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'number' => $invoice->number,
                    'amount' => $invoice->amount,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status->value,
                    'issuedAt' => $invoice->issued_at->toISOString(),
                ])
                ->all(),
            'supportAccess' => $supportAccess === null ? null : [
                'operator' => $supportAccess->operator->name,
                'grantedBy' => $supportAccess->grantedBy?->name,
                'reason' => $supportAccess->reason,
                'expiresAt' => $supportAccess->expires_at->toISOString(),
            ],
            'consoleActions' => $actions
                ->map(fn (ConsoleActionLog $entry) => [
                    'at' => $entry->created_at->toISOString(),
                    'actor' => $entry->actor_name,
                    'type' => $entry->type,
                ])
                ->all(),
        ];
    }

    /**
     * Get the state the console shows for the organisation, the most pressing first.
     */
    public static function status(Tenant $tenant): string
    {
        return match (true) {
            $tenant->deletion_scheduled_at !== null => 'deletion_scheduled',
            $tenant->isSuspended() => 'suspended',
            $tenant->subscription?->status === SubscriptionStatus::PastDue => 'past_due',
            $tenant->isOnTrial() => 'trial',
            default => 'active',
        };
    }
}
