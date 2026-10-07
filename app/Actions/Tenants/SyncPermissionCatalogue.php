<?php

namespace App\Actions\Tenants;

use App\Enums\StarterProfile;
use App\Enums\TenantPermission;
use App\Models\Profile;
use Spatie\Permission\PermissionRegistrar;

/**
 * Remet la base de l'organisation active au niveau du catalogue de permissions du code : cree les
 * permissions manquantes et les donne au Proprietaire, profil systeme qui detient tout le
 * catalogue (CLAUDE.md, « Profils et permissions »).
 *
 * Les profils de base (Tresorier, Hotesse, Lecture) retrouvent leurs reglages d'origine, et sont
 * recrees s'ils avaient ete supprimes : ils sont figes (decision du 2026-10-07). Les profils crees
 * par l'organisation ne recoivent rien d'office : c'est a elle de decider qui obtient une nouvelle
 * capacite, pas au deploiement.
 */
class SyncPermissionCatalogue
{
    public function __construct(
        private EnsurePermissionCatalogue $ensureCatalogue,
        private CreateStarterProfiles $starterProfiles,
    ) {
        //
    }

    public function handle(): void
    {
        $this->ensureCatalogue->handle();

        Profile::query()
            ->where('is_system', true)
            ->where('name', Profile::Owner)
            ->each(fn (Profile $owner) => $owner->syncPermissions(TenantPermission::values()));

        foreach (StarterProfile::cases() as $starter) {
            $this->starterProfiles->restore($starter);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
