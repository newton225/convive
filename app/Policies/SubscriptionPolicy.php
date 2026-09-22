<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

/**
 * L'abonnement d'une organisation (README ecran 16). `Subscription` vit dans la base centrale avec
 * un `tenant_id` : la verification d'appartenance est explicite (CLAUDE.md, « Ce qui reste central
 * malgre tout »), portee ici par la permission dans l'organisation visee.
 */
class SubscriptionPolicy
{
    /**
     * Determine whether the user can see the subscription screen.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::BillingView);
    }

    /**
     * Determine whether the user can change plan, payment method or cancel.
     */
    public function manage(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::BillingManage);
    }
}
