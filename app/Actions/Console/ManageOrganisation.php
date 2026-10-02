<?php

namespace App\Actions\Console;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantSuspension;
use App\Models\User;
use App\Support\Console\ConsoleJournal;
use App\Support\Console\TenantUsageRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Les actions de l'editeur sur une organisation (README section 3) : suspendre et reactiver,
 * changer de plan, offrir ou prolonger un essai, programmer ou annuler sa suppression. Chacune va au journal central, avec
 * l'avant et l'apres.
 */
class ManageOrganisation
{
    /**
     * Delai pendant lequel une suppression programmee reste annulable, en jours (README section 3).
     */
    public const DeletionDelayDays = 30;

    /**
     * Suspend the organisation by hand, with a mandatory reason.
     *
     * @throws ValidationException
     */
    public function suspend(Tenant $tenant, string $reason, User $actor): TenantSuspension
    {
        return DB::connection('central')->transaction(function () use ($tenant, $reason, $actor) {
            if ($tenant->suspension()->exists()) {
                throw ValidationException::withMessages(['reason' => __('console.organisation.errors.already_suspended')]);
            }

            $suspension = $tenant->suspension()->create([
                'reason' => $reason,
                'suspended_by_id' => $actor->id,
            ]);

            ConsoleJournal::record('tenant_suspended', $actor, $tenant, ['reason' => $reason]);

            return $suspension;
        });
    }

    /**
     * Lift the manual suspension. Une suspension pour impaye ne se leve pas d'ici : elle est
     * l'etat de l'abonnement, et se leve par le paiement.
     *
     * @throws ValidationException
     */
    public function reactivate(Tenant $tenant, User $actor): void
    {
        DB::connection('central')->transaction(function () use ($tenant, $actor) {
            $suspension = $tenant->suspension()->first();

            if ($suspension === null) {
                throw ValidationException::withMessages(['organisation' => __('console.organisation.errors.not_suspended_by_editor')]);
            }

            $suspension->update(['lifted_at' => now(), 'lifted_by_id' => $actor->id]);

            ConsoleJournal::record('tenant_reactivated', $actor, $tenant, ['reason' => $suspension->reason]);
        });
    }

    /**
     * Move the organisation to another plan.
     *
     * Une descente est refusee tant que la consommation depasse les quotas du plan vise, et le
     * message dit lesquels. Le changement est immediat dans les deux sens : seul un abonnement
     * regle en ligne a une periode payee a laisser courir, et celui-la ne se change pas d'ici. Un abonnement regle en ligne ne se change pas d'ici : le prestataire
     * continuerait de facturer l'ancien prix.
     *
     * @throws ValidationException
     */
    public function changePlan(Tenant $tenant, Plan $target, User $actor): void
    {
        $current = $tenant->plan();

        if ($current->is($target)) {
            throw ValidationException::withMessages(['plan' => __('console.organisation.errors.same_plan')]);
        }

        if ($tenant->subscription?->stripe_subscription_id !== null && $tenant->subscription->status !== SubscriptionStatus::Canceled) {
            throw ValidationException::withMessages(['plan' => __('console.organisation.errors.plan_paid_online')]);
        }

        if ($target->position < $current->position) {
            $this->refuseWhenUsageExceeds($tenant, $target);
        }

        DB::connection('central')->transaction(function () use ($tenant, $current, $target, $actor) {
            Subscription::updateOrCreate(
                ['tenant_id' => $tenant->id],
                // Un impaye ou une suspension en cours le restent : changer de plan n'efface pas
                // une dette.
                ['plan_id' => $target->id, 'status' => in_array($tenant->subscription?->status, [SubscriptionStatus::PastDue, SubscriptionStatus::Suspended], true)
                    ? $tenant->subscription->status
                    : SubscriptionStatus::Active],
            );

            ConsoleJournal::record('plan_changed', $actor, $tenant, [
                'old' => ['plan' => $current->code],
                'attributes' => ['plan' => $target->code],
                'detail' => $target->name,
            ]);
        });
    }

    /**
     * Offer or extend the trial of the organisation (README section 3). `$endsAt` nul : un essai
     * sans date de fin. Sans effet sur une organisation abonnee, dont l'abonnement l'emporte : on
     * le refuse plutot que de laisser croire qu'il s'applique.
     *
     * @throws ValidationException
     */
    public function extendTrial(Tenant $tenant, ?CarbonInterface $endsAt, User $actor): void
    {
        if ($tenant->subscription !== null) {
            throw ValidationException::withMessages(['ends_at' => __('console.organisation.errors.trial_has_subscription')]);
        }

        $before = $tenant->trial_ends_at?->toISOString();

        $tenant->forceFill([
            'trial_started_at' => $tenant->trial_started_at ?? now(),
            'trial_ends_at' => $endsAt,
        ])->save();

        ConsoleJournal::record('trial_extended', $actor, $tenant, [
            'old' => ['trial_ends_at' => $before],
            'attributes' => ['trial_ends_at' => $endsAt?->toISOString()],
        ]);
    }

    /**
     * Schedule the erasure of the organisation, at its written request (README section 3). It can
     * be cancelled until the date ; nothing is erased here.
     *
     * @throws ValidationException
     */
    public function scheduleDeletion(Tenant $tenant, string $requestReference, User $actor): void
    {
        if ($tenant->deletion_scheduled_at !== null) {
            throw ValidationException::withMessages(['request_reference' => __('console.organisation.errors.deletion_already_scheduled')]);
        }

        $tenant->forceFill(['deletion_scheduled_at' => now()->addDays(self::DeletionDelayDays)])->save();

        ConsoleJournal::record('deletion_scheduled', $actor, $tenant, [
            'request_reference' => $requestReference,
            'erase_at' => $tenant->deletion_scheduled_at?->toISOString(),
        ]);
    }

    /**
     * Cancel a scheduled erasure. Quand l'organisation a ete supprimee par son Proprietaire, c'est
     * une restauration : elle sort de la corbeille et ses membres la retrouvent.
     *
     * Returns true when the organisation was restored from the trash.
     *
     * @throws ValidationException
     */
    public function cancelDeletion(Tenant $tenant, User $actor): bool
    {
        if ($tenant->deletion_scheduled_at === null) {
            throw ValidationException::withMessages(['organisation' => __('console.organisation.errors.deletion_not_scheduled')]);
        }

        $restored = $tenant->trashed();

        $tenant->forceFill(['deletion_scheduled_at' => null])->save();

        if ($restored) {
            $tenant->restore();
            $this->restoreMemberships($tenant);
        }

        ConsoleJournal::record($restored ? 'tenant_restored' : 'deletion_cancelled', $actor, $tenant);

        return $restored;
    }

    /**
     * Give the organisation its members back. La suppression retire les appartenances centrales,
     * mais les profils restent affectes dans la base de l'organisation : c'est la qu'on relit qui
     * en etait membre, et chacun retrouve le profil qu'il portait.
     */
    private function restoreMemberships(Tenant $tenant): void
    {
        $userIds = $tenant->run(fn () => DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', (new User)->getMorphClass())
            ->pluck(config('permission.column_names.model_morph_key'))
            ->all());

        User::whereKey($userIds)->each(
            fn (User $user) => $tenant->memberships()->firstOrCreate(['user_id' => $user->id]),
        );
    }

    /**
     * @throws ValidationException
     */
    private function refuseWhenUsageExceeds(Tenant $tenant, Plan $target): void
    {
        // Releve a l'instant, pour cette seule organisation : la decision ne se prend pas sur un
        // compteur vieux d'un quart d'heure.
        $usage = TenantUsageRecorder::refresh($tenant);

        if ($usage === null) {
            throw ValidationException::withMessages(['plan' => __('console.organisation.errors.usage_unreadable')]);
        }

        $exceeded = collect([
            'active_events' => [$usage->active_events, $target->max_active_events],
            'registrations' => [$usage->registrations, $target->max_registrations],
            'members' => [$usage->members, $target->max_members],
        ])
            ->filter(fn (array $pair) => $pair[1] !== null && $pair[0] > $pair[1])
            ->map(fn (array $pair, string $quota) => __("console.organisation.errors.exceeded.{$quota}", ['used' => $pair[0], 'max' => $pair[1]]))
            ->values();

        if ($exceeded->isNotEmpty()) {
            throw ValidationException::withMessages([
                'plan' => __('console.organisation.errors.downgrade_refused', [
                    'plan' => $target->name,
                    'quotas' => $exceeded->implode(' ; '),
                ]),
            ]);
        }
    }
}
