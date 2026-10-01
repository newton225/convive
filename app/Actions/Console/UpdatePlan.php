<?php

namespace App\Actions\Console;

use App\Models\Plan;
use App\Models\User;
use App\Support\Console\ConsoleJournal;
use Illuminate\Support\Facades\DB;

/**
 * Modifier un plan du catalogue (README ecran 30) : prix et quotas. Un quota s'applique des
 * l'enregistrement a toutes les organisations du plan (`PlanLimits` le relit a chaque controle) ;
 * un prix ne vaut que pour les prochains paiements, le prestataire gardant celui de chaque
 * abonnement en cours. L'avant et l'apres vont au journal central.
 */
class UpdatePlan
{
    /**
     * Les colonnes que la console regle. Le reste (code, nom, position) appartient au code.
     *
     * @var array<int, string>
     */
    private const Editable = [
        'monthly_price', 'monthly_price_eur', 'monthly_price_usd',
        'max_active_events', 'max_registrations', 'max_members',
        'has_reconciliation', 'has_reports',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Plan $plan, array $attributes, User $actor): Plan
    {
        return DB::connection($plan->getConnectionName())->transaction(function () use ($plan, $attributes, $actor) {
            $before = $plan->only(self::Editable);

            $plan->fill(array_intersect_key($attributes, array_flip(self::Editable)));
            $plan->save();

            if ($plan->wasChanged()) {
                ConsoleJournal::record('plan_updated', $actor, null, [
                    'plan' => $plan->code,
                    'old' => $before,
                    'attributes' => $plan->only(self::Editable),
                ]);
            }

            return $plan;
        });
    }
}
