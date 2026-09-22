<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\StatementLine;
use App\Models\Tenant;
use App\Models\User;

/**
 * `StatementLine` vit dans la base du locataire. Policy separee de `StatementImportPolicy` : les
 * deux permissions (`reconciliation.import`, `reconciliation.resolve`) ne se recouvrent pas.
 */
class StatementLinePolicy
{
    /**
     * Determine whether the user can resolve the given line by hand.
     */
    public function resolve(User $user, StatementLine $line, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ReconciliationResolve);
    }
}
