<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;

/**
 * `SeatingTable` vit dans la base du locataire : une table resolue par liaison de route ne peut
 * venir d'aucune autre organisation que celle dont la base est active.
 */
class SeatingTablePolicy
{
    /**
     * Determine whether the user can view the seating plan of the tenant.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::SeatingView);
    }

    /**
     * Determine whether the user can move a registration to (or off) a table.
     */
    public function assign(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::SeatingAssign);
    }

    /**
     * Determine whether the user can change how many seats a table has.
     *
     * La modification de l'evenement, pas le placement : changer la taille d'une table change la
     * capacite de l'evenement, donc ce que le lien public offre aux invites.
     */
    public function resize(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::EventsUpdate);
    }
}
