<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;

/**
 * Le controle a l'entree (README ecran 26), etape 7 de « Ordre de construction ». Pas de modele
 * dedie a une tentative de scan : ces habilitations portent sur l'action elle-meme, comme
 * `SeatingTablePolicy::assign()`.
 */
class ScanPolicy
{
    /**
     * Determine whether the user can open the scan screen.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ScanPerform);
    }

    /**
     * Determine whether the user can submit a scanned code for verification.
     */
    public function perform(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ScanPerform);
    }

    /**
     * Determine whether the user can force an entry on a ticket already scanned.
     */
    public function force(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ScanForce);
    }

    /**
     * Determine whether the user can see the log of recent passages.
     */
    public function viewLog(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ScanLogView);
    }
}
