<?php

namespace App\Listeners;

use App\Support\Auth\RecoveryCodes;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

/**
 * Des que Fortify vient de creer des codes de secours, n'en garder que l'empreinte (SECURITY.md
 * M5). Les codes lisibles attendent en session leur unique affichage, sur l'ecran de securite.
 *
 * Synchrone, volontairement : entre la creation et ce passage, les codes sont lisibles en base.
 */
class SealRecoveryCodes
{
    public function handle(TwoFactorAuthenticationEnabled|RecoveryCodesGenerated $event): void
    {
        $codes = RecoveryCodes::seal($event->user);

        if ($codes !== [] && request()->hasSession()) {
            request()->session()->put(RecoveryCodes::SessionKey, $codes);
        }
    }
}
