<?php

namespace App\Actions\Tenants;

use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class DeleteTenantProfile
{
    /**
     * Delete a tenant profile and journal the removal.
     */
    public function handle(Profile $profile): void
    {
        DB::transaction(function () use ($profile) {
            activity()
                ->performedOn($profile)
                ->event('deleted')
                ->withProperties([
                    'old' => [
                        'name' => $profile->name,
                        'description' => $profile->description,
                        'permissions' => $profile->permissionValues(),
                        'requires_two_factor' => $profile->requires_two_factor,
                    ],
                ])
                ->log('profile.deleted');

            $profile->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
