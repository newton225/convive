<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;

/**
 * `PaymentAccount` vit dans la base du locataire : un compte resolu par liaison de route ne
 * peut venir d'aucune autre organisation que celle dont la base est active.
 */
class PaymentAccountPolicy
{
    /**
     * Determine whether the user can list the payment accounts of the tenant.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::TenantPaymentAccounts);
    }

    /**
     * Determine whether the user can create a payment account.
     */
    public function create(User $user, Tenant $tenant): bool
    {
        return $this->viewAny($user, $tenant);
    }

    /**
     * Determine whether the user can request a change on the given account.
     */
    public function update(User $user, PaymentAccount $account, Tenant $tenant): bool
    {
        return $this->viewAny($user, $tenant);
    }

    /**
     * Determine whether the user can lift the activation delay on a pending change.
     *
     * Deux conditions, et les deux comptent : c'est reserve a un Proprietaire, et jamais a
     * celui qui a demande le changement. Sans la seconde, un compte compromis se validerait
     * lui-meme et le delai ne protegerait plus de rien.
     */
    public function approve(User $user, PaymentAccount $account, Tenant $tenant): bool
    {
        return $account->hasPendingChange()
            && $account->pending_requested_by !== $user->id
            && $user->ownsTenant($tenant);
    }

    /**
     * Determine whether the user can cancel a pending change.
     */
    public function cancel(User $user, PaymentAccount $account, Tenant $tenant): bool
    {
        return $account->hasPendingChange()
            && $this->viewAny($user, $tenant);
    }

    /**
     * Determine whether the user can delete the given account.
     */
    public function delete(User $user, PaymentAccount $account, Tenant $tenant): bool
    {
        return $this->update($user, $account, $tenant);
    }
}
