<?php

namespace App\Actions\Tenants;

use App\Enums\PaymentChannel;
use App\Enums\TenantPermission;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\PaymentAccountChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Toute modification d'un compte de versement passe par ici.
 *
 * Trois garanties, non negociables (SECURITY.md C1) : la nouvelle valeur n'apparait pas
 * publiquement avant le delai d'activation, tout le monde qui compte est prevenu, et le
 * journal garde l'avant et l'apres.
 *
 * `PaymentAccount` vit dans la base du locataire (voir CLAUDE.md, « Multi-locataire ») : seule
 * `request()`, qui doit prevenir les porteurs de la permission, a encore besoin du `Tenant`
 * (donnee centrale). Les autres methodes n'operent que sur l'objet deja resolu.
 */
class SavePaymentAccount
{
    /**
     * Record a requested change, to take effect after the activation delay.
     *
     * @param  array{label: string, channel: string, account_number: ?string, holder_name: ?string, instructions: ?string, is_active: bool}  $attributes
     */
    public function request(Tenant $tenant, ?PaymentAccount $account, array $attributes, User $actor): PaymentAccount
    {
        return DB::transaction(function () use ($tenant, $account, $attributes, $actor) {
            $creating = $account === null;

            $account ??= new PaymentAccount;

            $before = $this->sensitiveValues($account);

            // Le libelle, la consigne et l'activation ne designent pas ou va l'argent : ils
            // prennent effet tout de suite. Seuls le canal, le numero et le titulaire attendent.
            $account->fill([
                'label' => $attributes['label'],
                'instructions' => $attributes['instructions'] ?? null,
                'is_active' => $attributes['is_active'],
            ]);

            $requested = [
                'channel' => PaymentChannel::from($attributes['channel']),
                'account_number' => $attributes['account_number'] ?? null,
                'holder_name' => $attributes['holder_name'] ?? null,
            ];

            $sensitiveChanged = $creating || $this->differsFromLive($account, $requested);

            if ($sensitiveChanged) {
                $account->pending_channel = $requested['channel'];
                $account->pending_account_number = $requested['account_number'];
                $account->pending_holder_name = $requested['holder_name'];
                $account->pending_activates_at = now()->addHours(PaymentAccount::ActivationDelayHours);
                $account->pending_requested_by = $actor->id;
                $account->pending_approved_by = null;
            }

            $account->save();

            activity()
                ->performedOn($account)
                ->event($creating ? 'created' : 'updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => $sensitiveChanged ? $this->pendingValues($account) : $this->sensitiveValues($account),
                    'pending' => $sensitiveChanged,
                ])
                ->log($sensitiveChanged ? 'payment_account.change_requested' : 'payment_account.updated');

            if ($sensitiveChanged) {
                $this->notifyWatchers($tenant, $account, $before, $actor);
            }

            return $account;
        });
    }

    /**
     * Let a second owner lift the activation delay.
     *
     * C'est la soupape prevue : corriger un numero la veille d'un evenement reste possible,
     * mais jamais par la seule personne qui a demande le changement.
     */
    public function approve(PaymentAccount $account, User $approver): PaymentAccount
    {
        return DB::transaction(function () use ($account, $approver) {
            $before = $this->sensitiveValues($account);

            $account->pending_approved_by = $approver->id;
            $this->apply($account);

            activity()
                ->performedOn($account)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => $this->sensitiveValues($account),
                    'approved_by' => $approver->id,
                ])
                ->log('payment_account.change_approved');

            return $account;
        });
    }

    /**
     * Move the pending values into the live ones.
     */
    public function apply(PaymentAccount $account): void
    {
        $account->channel = $account->pending_channel;
        $account->account_number = $account->pending_account_number;
        $account->holder_name = $account->pending_holder_name;

        $account->pending_channel = null;
        $account->pending_account_number = null;
        $account->pending_holder_name = null;
        $account->pending_activates_at = null;
        $account->pending_requested_by = null;

        $account->last_changed_at = now();
        $account->save();
    }

    /**
     * Drop a pending change without applying it. For an account that was never active, the pending
     * change is its creation : cancelling it deletes the account, rather than leaving a shell with
     * no channel and no number. Returns whether the account was deleted.
     */
    public function cancel(PaymentAccount $account, User $actor): bool
    {
        return DB::transaction(function () use ($account, $actor) {
            $discarded = $this->pendingValues($account);

            if ($account->neverActive()) {
                activity()
                    ->event('deleted')
                    ->withProperties([
                        'old' => ['label' => $account->label, ...$discarded],
                        'cancelled_by' => $actor->id,
                    ])
                    ->log('payment_account.creation_cancelled');

                $account->delete();

                return true;
            }

            $account->pending_channel = null;
            $account->pending_account_number = null;
            $account->pending_holder_name = null;
            $account->pending_activates_at = null;
            $account->pending_requested_by = null;
            $account->pending_approved_by = null;
            $account->save();

            activity()
                ->performedOn($account)
                ->event('updated')
                ->withProperties([
                    'old' => $discarded,
                    'cancelled_by' => $actor->id,
                ])
                ->log('payment_account.change_cancelled');

            return false;
        });
    }

    /**
     * Notify everyone who can see the money move, with the old and the new number.
     *
     * @param  array<string, string|null>  $before
     */
    private function notifyWatchers(Tenant $tenant, PaymentAccount $account, array $before, User $actor): void
    {
        $watchers = $tenant->members()->get()->filter(
            fn (User $member) => $member->hasTenantPermission($tenant, TenantPermission::TenantPaymentAccounts),
        );

        Notification::send(
            $watchers,
            new PaymentAccountChanged($tenant, $account, $before, $actor),
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function sensitiveValues(PaymentAccount $account): array
    {
        return [
            'channel' => $account->channel?->value,
            'account_number' => $account->account_number,
            'holder_name' => $account->holder_name,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function pendingValues(PaymentAccount $account): array
    {
        return [
            'channel' => $account->pending_channel?->value,
            'account_number' => $account->pending_account_number,
            'holder_name' => $account->pending_holder_name,
        ];
    }

    /**
     * @param  array{channel: PaymentChannel, account_number: ?string, holder_name: ?string}  $requested
     */
    private function differsFromLive(PaymentAccount $account, array $requested): bool
    {
        return $account->channel !== $requested['channel']
            || $account->account_number !== $requested['account_number']
            || $account->holder_name !== $requested['holder_name'];
    }
}
