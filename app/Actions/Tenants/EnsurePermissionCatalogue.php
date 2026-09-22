<?php

namespace App\Actions\Tenants;

use App\Enums\TenantPermission;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class EnsurePermissionCatalogue
{
    /**
     * Make sure every permission of the catalogue exists in database.
     *
     * Les permissions ne sont jamais creees depuis l'interface : elles viennent du code.
     * Cette action reconcilie la base avec l'enum, elle est idempotente.
     */
    public function handle(): void
    {
        $existing = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $missing = array_diff(TenantPermission::values(), $existing);

        if ($missing === []) {
            return;
        }

        Permission::insert(array_map(fn (string $name) => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ], array_values($missing)));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
