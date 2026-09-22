<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;

/**
 * `Registration` vit dans la base du locataire : une inscription resolue par liaison de route ne
 * peut venir d'aucune autre organisation que celle dont la base est active.
 */
class RegistrationPolicy
{
    /**
     * Determine whether the user can list the registrations of the tenant.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::RegistrationsView);
    }

    /**
     * Determine whether the user can view the given registration.
     */
    public function view(User $user, Registration $registration, Tenant $tenant): bool
    {
        return $this->viewAny($user, $tenant);
    }

    /**
     * Determine whether the user can export the tenant's registrations.
     */
    public function export(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::RegistrationsExport);
    }

    /**
     * Determine whether the user can purge unfinalized registrations.
     */
    public function purge(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::RegistrationsPurge);
    }

    /**
     * Determine whether the user can cancel the given registration.
     */
    public function cancel(User $user, Registration $registration, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::RegistrationsCancel);
    }
}
