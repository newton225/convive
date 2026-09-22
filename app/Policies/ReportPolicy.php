<?php

namespace App\Policies;

use App\Enums\PlanFeature;
use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlanLimits;

/**
 * Les rapports post-evenement (README ecran 22), etape 9 de « Ordre de construction ». Pas de
 * modele dedie : ces habilitations portent sur l'action elle-meme, comme `ScanPolicy`.
 *
 * Les rapports sont reserves aux plans qui les incluent (README section 3) : voir `planAllows()`.
 */
class ReportPolicy
{
    /**
     * Determine whether the user can see the post-event report.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $this->planAllows($tenant)
            && $user->hasTenantPermission($tenant, TenantPermission::ReportsView);
    }

    /**
     * Determine whether the user can export the post-event report.
     */
    public function export(User $user, Tenant $tenant): bool
    {
        return $this->planAllows($tenant)
            && $user->hasTenantPermission($tenant, TenantPermission::ReportsExport);
    }

    /**
     * Les rapports sont reserves aux plans qui les incluent (README section 3).
     */
    private function planAllows(Tenant $tenant): bool
    {
        return PlanLimits::for($tenant)->allows(PlanFeature::Reports);
    }
}
