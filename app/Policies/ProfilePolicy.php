<?php

namespace App\Policies;

use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;

/**
 * `Profile` vit dans la base du locataire : un profil resolu par liaison de route ne peut
 * venir d'aucune autre organisation que celle dont la base est active.
 */
class ProfilePolicy
{
    /**
     * Determine whether the user can list the profiles of the tenant.
     */
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ProfilesManage);
    }

    /**
     * Determine whether the user can create a profile in the tenant.
     */
    public function create(User $user, Tenant $tenant): bool
    {
        return $user->hasTenantPermission($tenant, TenantPermission::ProfilesManage);
    }

    /**
     * Determine whether the user can update the given profile.
     */
    public function update(User $user, Profile $profile, Tenant $tenant): bool
    {
        // Le profil systeme et les profils de base (Tresorier, Hotesse, Lecture) ne sont ni
        // modifiables ni supprimables, quel que soit le porteur (decision du 2026-10-07).
        if ($profile->isLocked()) {
            return false;
        }

        // On ne modifie pas le profil que l'on porte soi-meme : ce serait s'auto-elever.
        if ($user->tenantProfile($tenant)?->is($profile)) {
            return false;
        }

        return $user->hasTenantPermission($tenant, TenantPermission::ProfilesManage);
    }

    /**
     * Determine whether the user can delete the given profile.
     */
    public function delete(User $user, Profile $profile, Tenant $tenant): bool
    {
        return $this->update($user, $profile, $tenant);
    }

    /**
     * Determine whether the user can hide the given profile from every choice, or show it again.
     *
     * Seuls les profils de base se masquent : ils ne se suppriment pas. Un profil cree par
     * l'organisation se supprime, et le Proprietaire reste toujours propose.
     */
    public function hide(User $user, Profile $profile, Tenant $tenant): bool
    {
        return $profile->isStarter()
            && $user->hasTenantPermission($tenant, TenantPermission::ProfilesManage);
    }

    /**
     * Determine whether the user can assign profiles to the given member.
     */
    public function assign(User $user, Tenant $tenant, User $member): bool
    {
        // Un membre ne s'affecte pas un autre profil, meme s'il gere les profils.
        if ($user->is($member)) {
            return false;
        }

        // Le dernier Proprietaire actif ne peut pas perdre son profil.
        if ($member->ownsTenant($tenant) && $tenant->owners()->count() <= 1) {
            return false;
        }

        return $user->hasTenantPermission($tenant, TenantPermission::ProfilesManage);
    }
}
