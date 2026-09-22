<?php

namespace App\Actions\Tenants;

use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Opere sur les profils de l'organisation dont la base est active au moment de l'appel : la
 * tenancy est deja initialisee (voir CLAUDE.md, « Multi-locataire »), aucun `Tenant` n'a
 * besoin d'etre passe ici.
 */
class SaveTenantProfile
{
    /**
     * Create or update a tenant profile and journal the change.
     *
     * @param  array<int, string>  $permissions
     */
    public function handle(
        ?Profile $profile,
        string $name,
        ?string $description,
        array $permissions,
        bool $requiresTwoFactor = false,
    ): Profile {
        return DB::transaction(function () use ($profile, $name, $description, $permissions, $requiresTwoFactor) {
            $before = $profile === null ? null : [
                'name' => $profile->name,
                'description' => $profile->description,
                'permissions' => $profile->permissionValues(),
                'requires_two_factor' => $profile->requires_two_factor,
            ];

            $profile ??= new Profile(['guard_name' => 'web']);

            $profile->fill(['name' => $name, 'description' => $description]);
            $profile->guard_name = 'web';
            $profile->requires_two_factor = $requiresTwoFactor;
            $profile->save();

            $profile->syncPermissions($permissions);
            $profile->load('permissions');

            // Une modification de profil prend effet immediatement pour tous ses porteurs.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            activity()
                ->performedOn($profile)
                ->event($before === null ? 'created' : 'updated')
                ->withProperties(array_filter([
                    'old' => $before,
                    'attributes' => [
                        'name' => $profile->name,
                        'description' => $profile->description,
                        'permissions' => $profile->permissionValues(),
                        'requires_two_factor' => $profile->requires_two_factor,
                    ],
                ]))
                ->log($before === null ? 'profile.created' : 'profile.updated');

            return $profile;
        });
    }
}
