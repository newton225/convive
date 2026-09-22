<?php

namespace App\Actions\Tenants;

use App\Enums\TenantPermission;
use App\Models\Profile;
use Spatie\Permission\PermissionRegistrar;

/**
 * Opere sur les profils de l'organisation dont la base est active au moment de l'appel : voir
 * CLAUDE.md, « Multi-locataire ». Le seul appelant, `CreateTenant`, l'invoque a l'interieur de
 * `$tenant->run()`.
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
     * Les trois autres sont un point de depart que l'exploitant remanie a sa guise.
     *
     * Proprietaire et Tresorier exigent la double authentification : ce sont les deux profils
     * qui touchent a l'argent, aux comptes de versement et a la facturation.
     */
    public function handle(): Profile
    {
        $this->ensureCatalogue->handle();

        $owner = $this->make(Profile::Owner, __('profiles.starters.owner'), TenantPermission::values(), isSystem: true, requiresTwoFactor: true);

        $this->make('Tresorier', __('profiles.starters.treasurer'), $this->treasurerPermissions(), requiresTwoFactor: true);
        $this->make('Hotesse', __('profiles.starters.host'), $this->hostPermissions());
        $this->make('Lecture', __('profiles.starters.reader'), $this->readerPermissions());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $owner;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function make(string $name, string $description, array $permissions, bool $isSystem = false, bool $requiresTwoFactor = false): Profile
    {
        $profile = new Profile([
            'name' => $name,
            'description' => $description,
            'guard_name' => 'web',
        ]);

        $profile->is_system = $isSystem;
        $profile->requires_two_factor = $requiresTwoFactor;
        $profile->save();
        $profile->syncPermissions($permissions);

        return $profile;
    }

    /**
     * @return array<int, string>
     */
    private function treasurerPermissions(): array
    {
        return $this->values([
            TenantPermission::EventsView,
            TenantPermission::RegistrationsView,
            TenantPermission::RegistrationsExport,
            TenantPermission::ProofsView,
            TenantPermission::ProofsApprove,
            TenantPermission::ProofsReject,
            TenantPermission::ReconciliationImport,
            TenantPermission::ReconciliationResolve,
            TenantPermission::ReportsView,
            TenantPermission::ReportsExport,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function hostPermissions(): array
    {
        // Le forcage d'entree fait partie du poste d'accueil : il est autorise, et journalise.
        return $this->values([
            TenantPermission::EventsView,
            TenantPermission::ScanPerform,
            TenantPermission::ScanForce,
            TenantPermission::ScanLogView,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function readerPermissions(): array
    {
        return $this->values([
            TenantPermission::EventsView,
            TenantPermission::RegistrationsView,
            TenantPermission::ReportsView,
        ]);
    }

    /**
     * @param  array<int, TenantPermission>  $permissions
     * @return array<int, string>
     */
    private function values(array $permissions): array
    {
        return array_map(fn (TenantPermission $permission) => $permission->value, $permissions);
    }
}
