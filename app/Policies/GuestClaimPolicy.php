<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\GuestClaim;
use App\Models\Tenant;
use App\Models\User;

/**
 * `GuestClaim` vit dans la base du locataire : une reclamation resolue par liaison de route ne
 * peut venir d'aucune autre organisation que celle dont la base est active.
 */
class GuestClaimPolicy
{
    /**
     * Determine whether the user can list the claims of the tenant.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::RegistrationsClaims);
    }

    /**
     * Determine whether the user can mark the given claim as handled.
     */
    public function resolve(User $user, GuestClaim $claim, Tenant $tenant): bool
    {
        return $this->viewAny($user, $tenant);
    }
}
