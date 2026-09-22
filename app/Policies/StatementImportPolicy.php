<?php

namespace App\Policies;

use App\Enums\PlanFeature;
use App\Enums\TenantPermission;
use App\Models\StatementImport;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlanLimits;

/**
 * `StatementImport` vit dans la base du locataire : un import resolu ne peut venir d'aucune autre
 * organisation que celle dont la base est active.
 *
 * Le rapprochement est reserve aux plans qui l'incluent (README section 3) : voir `planAllows()`.
 */
class StatementImportPolicy
{
    /**
     * Determine whether the user can see the imported statements and their lines.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $this->planAllows($tenant)
            && $user->hasTenantPermission($tenant, TenantPermission::ReconciliationImport);
    }

    /**
     * Determine whether the user can import a statement.
     */
    public function import(User $user, Tenant $tenant): bool
    {
        return $this->planAllows($tenant)
            && $user->hasTenantPermission($tenant, TenantPermission::ReconciliationImport);
    }

    /**
     * Le rapprochement est reserve aux plans qui l'incluent (README section 3).
     */
    private function planAllows(Tenant $tenant): bool
    {
        return PlanLimits::for($tenant)->allows(PlanFeature::Reconciliation);
    }
}
