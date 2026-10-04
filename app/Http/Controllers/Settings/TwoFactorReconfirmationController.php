<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ConfirmTwoFactorReconfirmationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Rejeu du second facteur juste avant une action sensible (comptes de versement, SECURITY.md
 * C1), sur le meme principe que la reconfirmation de mot de passe de Fortify
 * (`ConfirmablePasswordController`), qui ne couvre que le mot de passe. Le moteur de
 * verification (`TwoFactorAuthenticationProvider`) est celui de Fortify : rien ne le
 * reimplemente (CLAUDE.md, « Respecter les bonnes pratiques de chaque outil »).
 */
class TwoFactorReconfirmationController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/confirm-two-factor');
    }

    public function store(ConfirmTwoFactorReconfirmationRequest $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $user = $request->user();
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        if (! $provider->verify($secret, (string) $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => __('account.two_factor.invalid_code'),
            ]);
        }

        $request->session()->put('auth.two_factor_confirmed_at', time());

        // Code saisi dans la fenetre d'une page (`EnsureRecentTwoFactorConfirmation`) : on y
        // revient, la saisie en cours intacte.
        if (! $request->session()->has('url.intended') && url()->previous() !== route('two-factor.reconfirm.show')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('account.two_factor_reconfirm.confirmed')]);

            return back();
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
