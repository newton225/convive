<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\PaymentProof;
use App\Models\Tenant;
use App\Models\User;

/**
 * `PaymentProof` vit dans la base du locataire : une preuve resolue par liaison de route ne
 * peut venir d'aucune autre organisation que celle dont la base est active.
 */
class PaymentProofPolicy
{
    /**
     * Determine whether the user can list the proofs of the tenant.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ProofsView);
    }

    /**
     * Determine whether the user can open the receipt of the given proof.
     */
    public function view(User $user, PaymentProof $proof, Tenant $tenant): bool
    {
        return $this->viewAny($user, $tenant);
    }

    /**
     * Determine whether the user can validate the given proof.
     */
    public function approve(User $user, PaymentProof $proof, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ProofsApprove);
    }

    /**
     * Determine whether the user can reject the given proof.
     */
    public function reject(User $user, PaymentProof $proof, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ProofsReject);
    }
}
