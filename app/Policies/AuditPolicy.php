<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;

/**
 * Le journal d'audit (README ecran 23). Lecture seule : le journal s'ecrit, il ne se modifie
 * jamais depuis l'interface (CLAUDE.md, « Journal d'audit inalterable »).
 */
class AuditPolicy
{
    /**
     * Determine whether the user can read the organisation's audit log.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::AuditView);
    }
}
