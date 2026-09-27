<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Le catalogue de permissions grandit avec le code (CLAUDE.md, « Profils et permissions ») : une
 * organisation ouverte avant l'ajout d'une permission doit la recevoir, et son Proprietaire, qui
 * detient tout le catalogue, avec elle. Sans cela, la nouvelle fonction reste fermee a tous ses
 * membres, Proprietaire compris.
 */
class SyncPermissionCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_permission_ajoutee_apres_l_ouverture_de_l_espace_est_creee_et_donnee_au_proprietaire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        // Simule une organisation ouverte avant l'arrivee de `events.announce`.
        $tenant->asCurrent(function () {
            Permission::where('name', TenantPermission::EventsAnnounce->value)->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        $this->artisan('tenants:sync-permissions')->assertSuccessful();

        $tenant->asCurrent(function () {
            $this->assertTrue(Permission::where('name', TenantPermission::EventsAnnounce->value)->exists());
            $this->assertTrue(
                Profile::where('name', Profile::Owner)->firstOrFail()->hasPermissionTo(TenantPermission::EventsAnnounce->value),
            );
        });
    }

    public function test_les_autres_profils_ne_recoivent_pas_la_nouvelle_permission_d_office(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $tenant->asCurrent(function () {
            Permission::where('name', TenantPermission::EventsAnnounce->value)->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        $this->artisan('tenants:sync-permissions')->assertSuccessful();

        $tenant->asCurrent(function () {
            $this->assertFalse(
                Profile::where('name', 'Lecture')->firstOrFail()->hasPermissionTo(TenantPermission::EventsAnnounce->value),
            );
        });
    }
}
