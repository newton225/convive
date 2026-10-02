<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CloseOtherSessionsRequest;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ScanPinRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Support\Auth\RecoveryCodes;
use App\Support\ConnectedDevices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'devices' => ConnectedDevices::for($request->user(), $request->session()->getId()),
            'hasScanPin' => $request->user()->scan_pin_verifier !== null,
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
            $props['recoveryCodesRemaining'] = RecoveryCodes::remaining($request->user());
            // Affiches une seule fois (SECURITY.md M5), et seulement quand la double
            // authentification est en service : avant sa confirmation, l'ecran ne les montrerait
            // pas et ils seraient perdus.
            $props['freshRecoveryCodes'] = $props['twoFactorEnabled']
                ? $request->session()->pull(RecoveryCodes::SessionKey)
                : null;

            if ($request->user()->two_factor_secret === null) {
                $request->session()->forget(RecoveryCodes::SessionKey);
            }
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        // Les autres sessions ouvertes (appareil perdu, session volee) ne survivent pas a un
        // changement de mot de passe (SECURITY.md, « Deconnexion et sessions »).
        Auth::logoutOtherDevices((string) $request->password);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('account.flash.password_updated')]);

        return back();
    }

    /**
     * Choose or replace the member's scan code (SECURITY.md M8).
     */
    public function updateScanPin(ScanPinRequest $request): RedirectResponse
    {
        $request->user()->setScanPin((string) $request->validated('pin'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('account.scan_pin.flash')]);

        return back();
    }

    /**
     * Close every other session of the user (SECURITY.md, « Deconnexion et sessions »).
     */
    public function destroyOtherSessions(CloseOtherSessionsRequest $request): RedirectResponse
    {
        Auth::logoutOtherDevices((string) $request->validated('password'));

        $closed = ConnectedDevices::closeOthers($request->user(), $request->session()->getId());

        activity()
            ->causedBy($request->user())
            ->event('deleted')
            ->withProperties(['count' => $closed])
            ->log('account.other_sessions_closed');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('account.devices.flash')]);

        return back();
    }
}
