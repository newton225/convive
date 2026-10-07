<?php

namespace App\Actions\Tenants;

use App\Enums\StarterProfile;
use App\Enums\TenantPermission;
use App\Models\Profile;
use Spatie\Permission\PermissionRegistrar;

/**
 * Opere sur les profils de l'organisation dont la base est active au moment de l'appel : voir
 * CLAUDE.md, « Multi-locataire ». Appele par `CreateTenant` a l'interieur de `$tenant->run()`, et
 * par `SyncPermissionCatalogue` pour remettre les profils de base a leurs reglages d'origine.
 */
class CreateStarterProfiles
{
    public function __construct(private EnsurePermissionCatalogue $ensureCatalogue)
    {
        //
    }

    /**
     * Create the profiles a tenant starts with.
     *
     * Proprietaire est un profil systeme : il detient tout le catalogue et ne se modifie pas.
     * Tresorier, Hotesse et Lecture sont des profils de base, figes eux aussi
     * (`StarterProfile`) : une organisation qui veut une variante cree son propre profil.
     *
     * Proprietaire et Tresorier exigent la double authentification : ce sont les deux profils
     * qui touchent a l'argent, aux comptes de versement et a la facturation.
     */
    public function handle(): Profile
    {
        $this->ensureCatalogue->handle();

        $owner = new Profile([
            'name' => Profile::Owner,
            'description' => __('profiles.starters.owner'),
            'guard_name' => 'web',
        ]);
        $owner->is_system = true;
        $owner->requires_two_factor = true;
        $owner->save();
        $owner->syncPermissions(TenantPermission::values());

        foreach (StarterProfile::cases() as $starter) {
            $this->restore($starter);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $owner;
    }

    /**
     * Create the starter profile, or give it back its original settings.
     *
     * Le profil est retrouve par sa colonne `starter`, puis par son nom pour une organisation
     * ouverte avant que la colonne n'existe. Seul le masquage, choix de l'organisation, est garde.
     */
    public function restore(StarterProfile $starter): Profile
    {
        $profile = Profile::query()->where('starter', $starter->value)->first()
            ?? Profile::query()->where('is_system', false)->where('name', $starter->profileName())->first()
            ?? new Profile(['guard_name' => 'web']);

        $profile->fill([
            'name' => $starter->profileName(),
            'description' => $starter->description(),
        ]);
        $profile->guard_name = 'web';
        $profile->is_system = false;
        $profile->starter = $starter;
        $profile->requires_two_factor = $starter->requiresTwoFactor();
        $profile->save();
        $profile->syncPermissions($starter->permissionValues());

        return $profile;
    }
}
