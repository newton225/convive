<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;

// `Unit` vit dans la base du locataire : une unite resolue par liaison de route ne peut
// venir d'aucune autre organisation que celle dont la base est active.
class UnitPolicy
{
    /**
     * Determine whether the user can list the units of the tenant.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::TenantUnits);
    }

    /**
     * Determine whether the user can create a unit in the tenant.
     */
    public function create(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::TenantUnits);
    }

    /**
     * Determine whether the user can update the given unit.
     *
     * « Aucune » ne se renomme, ne se desactive ni ne se supprime (decision du 2026-10-07) : c'est
     * le choix de qui n'appartient a aucune unite, il doit toujours etre propose.
     */
    public function update(User $user, Unit $unit, Tenant $tenant): bool
    {
        return ! $unit->isNone()
            && $user->hasTenantPermission($tenant, TenantPermission::TenantUnits);
    }

    /**
     * Determine whether the user can delete the given unit.
     */
    public function delete(User $user, Unit $unit, Tenant $tenant): bool
    {
        return $this->update($user, $unit, $tenant);
    }
}
