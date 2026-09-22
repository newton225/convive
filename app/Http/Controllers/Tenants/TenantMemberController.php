<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\UpdateTenantMemberRequest;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TenantMemberController extends Controller
{
    /**
     * Update the profile carried by the specified member.
     */
    public function update(UpdateTenantMemberRequest $request, Tenant $tenant, User $member): RedirectResponse
    {
        Gate::authorize('assign', [Profile::class, $tenant, $member]);

        abort_unless($tenant->memberships()->where('user_id', $member->id)->exists(), 404);

        // La tenancy est deja active pour ce locataire a ce point de la requete
        // (EnsureTenantMembership) : Profile se resout directement, sans filtre a poser.
        $profile = Profile::findOrFail((int) $request->validated('profile_id'));

        $previous = $member->tenantProfile($tenant);
        $member->assignTenantProfile($tenant, $profile);

        activity()
            ->performedOn($member)
            ->event('updated')
            ->withProperties([
                'old' => ['profile' => $previous?->name],
                'attributes' => ['profile' => $profile->name],
                'tenant_id' => $tenant->id,
            ])
            ->log('tenant_member.profile_assigned');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('tenants.flash.member_profile_updated')]);

        return to_route('tenants.edit', ['tenant' => $tenant->slug]);
    }

    /**
     * Remove the specified tenant member.
     */
    public function destroy(Tenant $tenant, User $member): RedirectResponse
    {
        Gate::authorize('removeMember', $tenant);

        abort_if($tenant->owner()?->is($member), 403, __('tenants.errors.owner_cannot_be_removed'));

        $tenant->memberships()
            ->where('user_id', $member->id)
            ->delete();

        $member->removeTenantProfile($tenant);

        if ($member->isCurrentTenant($tenant)) {
            $member->switchTenant($member->personalTenant());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('tenants.flash.member_removed')]);

        return to_route('tenants.edit', ['tenant' => $tenant->slug]);
    }
}
