<?php

namespace App\Http\Controllers\Tenants;

use App\Actions\Tenants\DeleteTenantProfile;
use App\Actions\Tenants\SaveTenantProfile;
use App\Enums\TenantPermission;
use App\Enums\TenantPermissionDomain;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\SaveProfileRequest;
use App\Models\Profile;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the profiles of the tenant.
     */
    public function index(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('viewAny', [Profile::class, $tenant]);

        return Inertia::render('tenants/profiles', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'profiles' => $this->profilesFor($tenant),
            // Pour la vue comparative profils x permissions (prototype Convive.dc.html).
            'catalogue' => $this->catalogue(),
        ]);
    }

    /**
     * Show the profile editor for a new profile : a name, then one block of actions per module.
     */
    public function create(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('create', [Profile::class, $tenant]);

        return $this->editor($request, $tenant, null);
    }

    /**
     * Show the profile editor for an existing profile.
     */
    public function edit(Request $request, Tenant $tenant, Profile $profile): Response
    {
        Gate::authorize('update', [$profile, $tenant]);

        return $this->editor($request, $tenant, $profile);
    }

    /**
     * Store a newly created profile.
     */
    public function store(SaveProfileRequest $request, Tenant $tenant, SaveTenantProfile $saveProfile): RedirectResponse
    {
        Gate::authorize('create', [Profile::class, $tenant]);

        $saveProfile->handle(
            null,
            $request->validated('name'),
            $request->validated('description'),
            $request->validated('permissions'),
            $request->boolean('requires_two_factor'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profiles.flash.created')]);

        return to_route('tenants.profiles.index', $tenant);
    }

    /**
     * Update the specified profile.
     */
    public function update(SaveProfileRequest $request, Tenant $tenant, Profile $profile, SaveTenantProfile $saveProfile): RedirectResponse
    {
        Gate::authorize('update', [$profile, $tenant]);

        $saveProfile->handle(
            $profile,
            $request->validated('name'),
            $request->validated('description'),
            $request->validated('permissions'),
            $request->boolean('requires_two_factor'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profiles.flash.updated')]);

        return to_route('tenants.profiles.index', $tenant);
    }

    /**
     * Duplicate the specified profile as a starting point for a variant.
     */
    public function duplicate(Request $request, Tenant $tenant, Profile $profile, SaveTenantProfile $saveProfile): RedirectResponse
    {
        Gate::authorize('create', [Profile::class, $tenant]);

        $saveProfile->handle(
            null,
            __('profiles.duplicate_name', ['name' => $profile->name]),
            $profile->description,
            array_intersect($profile->permissionValues(), $request->user()->tenantPermissionValues($tenant)),
            $profile->demandsTwoFactor(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profiles.flash.duplicated')]);

        return to_route('tenants.profiles.index', $tenant);
    }

    /**
     * Delete the specified profile.
     */
    public function destroy(Tenant $tenant, Profile $profile, DeleteTenantProfile $deleteProfile): RedirectResponse
    {
        Gate::authorize('delete', [$profile, $tenant]);

        $memberCount = $profile->members()->count();

        if ($memberCount > 0) {
            return back()->withErrors([
                'profile' => __('profiles.errors.still_assigned', ['count' => $memberCount]),
            ]);
        }

        $deleteProfile->handle($profile);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('profiles.flash.deleted')]);

        return to_route('tenants.profiles.index', $tenant);
    }

    private function editor(Request $request, Tenant $tenant, ?Profile $profile): Response
    {
        return Inertia::render('tenants/profile-form', [
            'tenant' => ['slug' => $tenant->slug],
            'profile' => $profile === null ? null : [
                'id' => $profile->id,
                'name' => $profile->name,
                'description' => $profile->description,
                'requiresTwoFactor' => $profile->demandsTwoFactor(),
                'permissions' => $profile->permissionValues(),
                'memberCount' => $profile->members()->count(),
            ],
            'catalogue' => $this->catalogue(),
            'heldPermissions' => $request->user()->tenantPermissionValues($tenant),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function profilesFor(Tenant $tenant): array
    {
        // La tenancy est deja active pour ce locataire (EnsureTenantMembership) : Profile se
        // resout directement, sans filtre a poser.
        return Profile::query()
            ->with('permissions')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Profile $profile) => [
                'id' => $profile->id,
                'name' => $profile->name,
                'description' => $profile->description,
                'isSystem' => $profile->is_system,
                'requiresTwoFactor' => $profile->demandsTwoFactor(),
                'permissions' => $profile->permissionValues(),
                'memberCount' => $profile->members()->count(),
            ])
            ->all();
    }

    /**
     * The fixed permission catalogue, grouped by domain, as offered to the operator.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(): array
    {
        return array_map(fn (TenantPermissionDomain $domain) => [
            'value' => $domain->value,
            'label' => $domain->label(),
            'permissions' => array_map(fn (TenantPermission $permission) => [
                'value' => $permission->value,
                'label' => $permission->label(),
                'action' => $permission->actionLabel(),
                'requires' => $permission->prerequisite()?->value,
            ], $domain->permissions()),
        ], TenantPermissionDomain::cases());
    }
}
