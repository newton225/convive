<?php

namespace App\Jobs;

use App\Actions\Notifications\SendAlert;
use App\Enums\NotificationType;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Console\TenantUsageRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Previent les organisations d'un plan dont une limite vient d'etre abaissee, quand elles la
 * depassent. Sans cette alerte, elles ne l'apprendraient qu'en voyant une publication, une
 * inscription ou une invitation refusee.
 *
 * En file : il faut ouvrir la base de chaque organisation du plan pour relever sa consommation,
 * ce que la requete de l'editeur n'a pas a attendre. Une organisation qui reste sous les nouvelles
 * limites n'est pas derangee.
 */
class AlertOrganisationsOverPlanLimits implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $loweredQuotas  les colonnes du plan qui ont baisse (`max_active_events`...)
     */
    public function __construct(public int $planId, public array $loweredQuotas) {}

    public function handle(SendAlert $alerts): void
    {
        $plan = Plan::find($this->planId);

        if ($plan === null) {
            return;
        }

        Tenant::query()
            ->with('subscription.plan')
            ->get()
            // Le plan effectif : celui de l'abonnement, de l'essai ou le plan par defaut.
            ->filter(fn (Tenant $tenant) => $tenant->plan()->is($plan))
            ->each(function (Tenant $tenant) use ($plan, $alerts) {
                if (! $this->exceeds($tenant, $plan)) {
                    return;
                }

                // L'alerte s'adresse a l'equipe de l'organisation : elle part sous sa tenancy.
                $tenant->run(fn () => $alerts->toTenantMembers(
                    NotificationType::PlanLimitsLowered,
                    ['plan' => $plan->name],
                    route('tenants.billing.show', $tenant, absolute: false),
                ));
            });
    }

    private function exceeds(Tenant $tenant, Plan $plan): bool
    {
        // Une base illisible est signalee par la sante technique ; ici, on ne previent pas a tort.
        $usage = TenantUsageRecorder::refresh($tenant);

        if ($usage === null) {
            return false;
        }

        $used = [
            'max_active_events' => $usage->active_events,
            'max_registrations' => $usage->registrations,
            'max_members' => $usage->members,
        ];

        foreach ($this->loweredQuotas as $quota) {
            $max = $plan->getAttribute($quota);

            if ($max !== null && isset($used[$quota]) && $used[$quota] > $max) {
                return true;
            }
        }

        return false;
    }
}
