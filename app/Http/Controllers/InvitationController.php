<?php

namespace App\Http\Controllers;

use App\Models\TenantInvitation;
use App\Support\PendingTenantInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'accueil des invitations (TODO du 2026-10-07, points 8 et 10) : ou arrive une personne inscrite
 * pour rejoindre une organisation, un compte sans organisation, ou quiconque a suivi le lien d'une
 * invitation et ne l'a pas encore tranchee. Rien n'est rattache sans un clic sur « Accepter ».
 */
class InvitationController extends Controller
{
    /**
     * Show the invitations addressed to the user, and the one followed from an email when it is
     * addressed to another address.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $invitations = TenantInvitation::query()
            ->with(['tenant', 'inviter'])
            ->whereRaw('lower(email) = ?', [strtolower($user->email)])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get();

        // L'invitation suivie depuis le courriel, adressee a une autre adresse que celle du compte :
        // jamais rattachee, mais expliquee, et gardee tant que la personne n'a pas choisi.
        $followed = PendingTenantInvitation::current($request);
        $mismatch = $followed !== null && strcasecmp($followed->email, $user->email) !== 0 ? [
            'code' => $followed->code,
            'tenantName' => $followed->tenant->name,
            'invitedEmail' => $followed->email,
            'accountEmail' => $user->email,
        ] : null;

        $tenant = $user->currentTenant ?? $user->personalTenant() ?? $user->tenants()->first();

        return Inertia::render('invitations', [
            'invitations' => $invitations->map(fn (TenantInvitation $invitation) => [
                'code' => $invitation->code,
                'tenantName' => $invitation->tenant->name,
                'profileName' => $invitation->profile_name,
                'inviterName' => $invitation->inviter->name,
                'expiresAt' => $invitation->expires_at?->toISOString(),
            ])->values()->all(),
            'mismatch' => $mismatch,
            'hasOrganisation' => $tenant !== null,
            'dashboardUrl' => $tenant !== null ? route('dashboard', $tenant, absolute: false) : null,
        ]);
    }

    /**
     * Set aside the invitation followed from an email.
     */
    public function forget(Request $request): RedirectResponse
    {
        PendingTenantInvitation::forget($request);

        return to_route('home');
    }
}
