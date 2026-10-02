<?php

namespace App\Http\Requests\Auth;

use App\Support\Auth\RecoveryCodes;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyTwoFactorLoginRequest;

/**
 * La verification du second facteur a la connexion, avec des codes de secours gardes sous forme
 * d'empreinte (SECURITY.md M5).
 *
 * Fortify compare le code saisi a ce qui est stocke : avec des empreintes en base, il accepterait
 * l'empreinte elle-meme comme code. Fortify ne prevoit pas de point d'extension pour cette
 * comparaison ; sa requete est donc remplacee dans le conteneur (`FortifyServiceProvider`), seule
 * maniere de la changer sans toucher au paquet (CLAUDE.md, « Respecter les bonnes pratiques de
 * chaque outil » : SECURITY.md l'emporte).
 */
class TwoFactorLoginRequest extends FortifyTwoFactorLoginRequest
{
    /**
     * Get the stored fingerprint of the recovery code on the request, if it is a valid one.
     *
     * @return string|null
     */
    public function validRecoveryCode()
    {
        $code = $this->input('recovery_code');

        if (! is_string($code) || $code === '') {
            return null;
        }

        $fingerprint = RecoveryCodes::match($this->challengedUser(), $code);

        if ($fingerprint !== null) {
            $this->session()->forget('login.id');
        }

        return $fingerprint;
    }
}
