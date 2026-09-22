<?php

namespace App\Http\Controllers\Tenants;

use App\Actions\Notifications\SendAlert;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\CreateTenantInvitationRequest;
use App\Http\Requests\Tenants\RespondToTenantInvitationRequest;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\Tenants\TenantInvitation as TenantInvitationNotification;
use App\Support\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TenantInvitationController extends Controller
{
    /**
     * Store a newly created invitation.
     */
    public function store(CreateTenantInvitationRequest $request, Tenant $tenant): RedirectResponse
    {
        Gate::authorize('inviteMember', $tenant);

        if (! PlanLimits::for($tenant)->canAddMember()) {
            throw ValidationException::withMessages([
                'email' => __('billing.errors.member_quota', ['plan' => $tenant->plan()->name]),
            ]);
        }

        // La tenancy est deja active pour ce locataire (EnsureTenantMembership) : Profile se
        // resout directement. Le nom est duplique sur l'invitation, qui vit dans la base
        // centrale : afficher l'invitation ne doit pas exiger de rejoindre celle du locataire.
        $profileName = Profile::findOrFail((int) $request->validated('profile_id'))->name;

        $invitation = $tenant->invitations()->create([
            'email' => $request->validated('email'),
            'profile_id' => $request->validated('profile_id'),
            'profile_name' => $profileName,
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new TenantInvitationNotification($invitation));

        // Une invitation a quelqu'un qui a deja un compte apparait aussi dans sa cloche (README
        // section 5, « invitation d'equipe en attente ») ; sans compte, le courriel suffit.
        $invitee = User::where('email', $invitation->email)->first();

        if ($invitee !== null) {
            app(SendAlert::class)->toUser(
                $invitee,
                NotificationType::TeamInvitationPending,
                ['tenant' => $tenant->name, 'profile' => $profileName],
                route('tenants.index', absolute: false),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('tenants.flash.invitation_sent')]);

        return to_route('tenants.edit', ['tenant' => $tenant->slug]);
    }

    /**
     * Cancel the specified invitation.
     */
    public function destroy(Tenant $tenant, TenantInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->tenant_id === $tenant->id, 404);

        Gate::authorize('cancelInvitation', $tenant);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('tenants.flash.invitation_cancelled')]);

        return to_route('tenants.edit', ['tenant' => $tenant->slug]);
    }

    /**
     * Accept the invitation.
     */
    public function accept(RespondToTenantInvitationRequest $request, TenantInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $invitation) {
            $tenant = $invitation->tenant;

            $profile = $tenant->run(fn () => Profile::findOrFail($invitation->profile_id));

            $tenant->addMember($user, $profile);

            $invitation->update(['accepted_at' => now()]);

            $user->switchTenant($tenant);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('tenants.flash.invitation_accepted')]);

        return to_route('dashboard');
    }

    /**
     * Decline the invitation.
     */
    public function decline(RespondToTenantInvitationRequest $request, TenantInvitation $invitation): RedirectResponse
    {
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('tenants.flash.invitation_declined')]);

        return to_route('dashboard');
    }
}
