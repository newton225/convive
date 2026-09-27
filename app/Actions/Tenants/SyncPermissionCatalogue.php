<?php

namespace App\Actions\Tenants;

use App\Enums\TenantPermission;
use App\Models\Profile;
use Spatie\Permission\PermissionRegistrar;

/**
 * Remet la base de l'organisation active au niveau du catalogue de permissions du code : cree les
 * permissions manquantes et les donne au Proprietaire, profil systeme qui detient tout le
 * catalogue (CLAUDE.md, « Profils et permissions »).
 *
 * Les autres profils ne recoivent rien d'office : c'est a l'organisation de decider qui obtient
 * une nouvelle capacite, pas au deploiement.
 */
class SyncPermissionCatalogue
{
    public function __construct(private EnsurePermissionCatalogue $ensureCatalogue)
    {
        //
    }

    public function handle(): void
    {
        $this->ensureCatalogue->handle();

        Profile::query()
            ->where('is_system', true)
            ->where('name', Profile::Owner)
            ->each(fn (Profile $owner) => $owner->syncPermissions(TenantPermission::values()));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
