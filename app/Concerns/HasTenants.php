<?php

namespace App\Concerns;

use App\Data\TenantPermissions;
use App\Data\UserTenant;
use App\Enums\TenantPermission;
use App\Models\Membership;
use App\Models\Profile;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;

/**
 * L'appartenance (quels locataires, quel profil dans chacun) est une donnee centrale
 * (`tenant_members`), mais le profil affecte et ses permissions vivent dans la base du
 * locataire (voir CLAUDE.md, « Multi-locataire »). Ce trait ne s'appuie plus sur
 * `spatie/laravel-permission` cote utilisateur : `HasRoles` suppose que le porteur et le role
 * partagent une connexion, ce qui n'est plus vrai des qu'ils vivent dans deux bases separees.
 * `Profile` (cote locataire) continue d'etendre `Role` et d'utiliser le paquet normalement
 * pour son propre catalogue de permissions ; seule la relation vers l'utilisateur (central)
 * est reecrite ici, a la main, sur la table pivot `model_has_profiles`.
 */
trait HasTenants
{
    /**
     * Get all of the tenants the user belongs to.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_members', 'user_id', 'tenant_id')
            ->withTimestamps();
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current tenant.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    /**
     * Get the profile the user carries in the given tenant.
     */
    public function tenantProfile(Tenant $tenant): ?Profile
    {
        return $tenant->run(function () {
            $profileId = DB::table('model_has_profiles')
                ->where('model_type', $this->getMorphClass())
                ->where('model_id', $this->getKey())
                ->value('profile_id');

            return $profileId ? Profile::query()->whereKey($profileId)->first() : null;
        });
    }

    /**
     * Give the user the given profile in the given tenant, replacing any previous one.
     *
     * Un profil a la fois : l'affectation precedente est retiree avant que la nouvelle ne soit
     * ecrite, jamais les deux a la fois.
     */
    public function assignTenantProfile(Tenant $tenant, Profile $profile): void
    {
        $tenant->run(function () use ($profile) {
            DB::table('model_has_profiles')
                ->where('model_type', $this->getMorphClass())
                ->where('model_id', $this->getKey())
                ->delete();

            DB::table('model_has_profiles')->insert([
                'profile_id' => $profile->id,
                'model_type' => $this->getMorphClass(),
                'model_id' => $this->getKey(),
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /**
     * Remove any profile the user carries in the given tenant.
     */
    public function removeTenantProfile(Tenant $tenant): void
    {
        $tenant->run(function () {
            DB::table('model_has_profiles')
                ->where('model_type', $this->getMorphClass())
                ->where('model_id', $this->getKey())
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /**
     * Determine whether the user holds the given permission in the given tenant.
     */
    public function hasTenantPermission(Tenant $tenant, TenantPermission $permission): bool
    {
        return in_array($permission->value, $this->tenantPermissionValues($tenant), true);
    }

    /**
     * Get the permission values the user effectively holds in the given tenant.
     *
     * @return array<int, string>
     */
    public function tenantPermissionValues(Tenant $tenant): array
    {
        // `permissionValues()` accede a la relation `permissions`, qui se charge en retard :
        // l'appeler apres le retour de `tenantProfile()` la chargerait hors du contexte de
        // locataire pose par `run()`, des que rien d'autre ne le maintient actif (donc hors
        // d'une requete authentifiee, comme dans un test qui appelle cette methode seule).
        $own = $tenant->run(fn () => $this->tenantProfile($tenant)?->permissionValues());

        if ($own !== null) {
            return $own;
        }

        // Sans profil dans l'organisation : rien, sauf la lecture qu'ouvre un acces de support.
        return $this->supportAccessTo($tenant) !== null
            ? array_map(fn (TenantPermission $permission) => $permission->value, TenantPermission::supportReadable())
            : [];
    }

    /**
     * Get the support access currently letting the user read the given tenant, if any (README
     * section 3). Deux conditions, relues a chaque appel : l'utilisateur fait toujours partie de
     * l'equipe Convive, et un Proprietaire lui a ouvert un acces ni revoque ni echu.
     */
    public function supportAccessTo(Tenant $tenant): ?SupportAccessGrant
    {
        if (! Gate::forUser($this)->allows('console.access')) {
            return null;
        }

        return SupportAccessGrant::query()
            ->where('tenant_id', $tenant->id)
            ->where('operator_id', $this->getKey())
            ->active()
            ->latest('id')
            ->first();
    }

    /**
     * Determine whether the user belongs to the given tenant.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenants.id', $tenant->id)->exists();
    }

    /**
     * Determine if the given tenant is the user's current tenant.
     */
    public function isCurrentTenant(Tenant $tenant): bool
    {
        return $this->current_tenant_id === $tenant->id;
    }

    /**
     * Determine whether the user carries the system owner profile of the given tenant.
     */
    public function ownsTenant(Tenant $tenant): bool
    {
        return $this->tenantProfile($tenant)?->isOwner() ?? false;
    }

    /**
     * Get the tenants the user owns, that is, where they carry the system owner profile.
     *
     * Une base par locataire (voir CLAUDE.md, « Multi-locataire ») : la question ne se
     * repond plus par une seule requete a travers une table partagee, elle se pose une fois
     * par organisation dont l'utilisateur est membre.
     *
     * @return Collection<int, Tenant>
     */
    public function ownedTenants(): Collection
    {
        return $this->tenants()->get()->filter(
            fn (Tenant $tenant) => $this->ownsTenant($tenant),
        )->values();
    }

    /**
     * Get the user's personal tenant.
     */
    public function personalTenant(): ?Tenant
    {
        return $this->ownedTenants()->first(fn (Tenant $tenant) => $tenant->is_personal);
    }

    /**
     * Switch to the given tenant.
     */
    public function switchTenant(Tenant $tenant): bool
    {
        if (! $this->belongsToTenant($tenant)) {
            return false;
        }

        $this->update(['current_tenant_id' => $tenant->id]);
        $this->setRelation('currentTenant', $tenant);

        URL::defaults(['current_tenant' => $tenant->slug]);

        return true;
    }

    /**
     * Get the tenant the user should fall back to, excluding the given one.
     */
    public function fallbackTenant(?Tenant $excluding = null): ?Tenant
    {
        return $this->tenants()
            ->when($excluding, fn ($query) => $query->where('tenants.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(tenants.name)')
            ->first();
    }

    /**
     * Get the user's tenants as a collection of UserTenant objects.
     *
     * @return Collection<int, UserTenant>
     */
    public function toUserTenants(bool $includeCurrent = false): Collection
    {
        return $this->tenants()
            ->get()
            ->map(fn (Tenant $tenant) => ! $includeCurrent && $this->isCurrentTenant($tenant) ? null : $this->toUserTenant($tenant))
            ->filter()
            ->values();
    }

    /**
     * Get the user's tenant as a UserTenant object.
     */
    public function toUserTenant(Tenant $tenant): UserTenant
    {
        $profile = $this->tenantProfile($tenant);

        return new UserTenant(
            id: $tenant->id,
            name: $tenant->name,
            slug: $tenant->slug,
            isPersonal: $tenant->is_personal,
            profileId: $profile?->id,
            profileName: $profile?->name,
            isOwner: $profile?->isOwner() ?? false,
            isCurrent: $this->isCurrentTenant($tenant),
            planName: $tenant->plan()->name,
        );
    }

    /**
     * Get the effective permissions of the user on the given tenant.
     */
    public function toTenantPermissions(Tenant $tenant): TenantPermissions
    {
        return new TenantPermissions($this->tenantPermissionValues($tenant));
    }
}
