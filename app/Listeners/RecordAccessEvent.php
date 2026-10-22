<?php

namespace App\Listeners;

use App\Models\SecurityEvent;
use App\Support\Console\SecurityJournal;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * Retracer les acces : chaque connexion, reussie ou non, chaque deconnexion et chaque geste sur les
 * moyens de s'authentifier (double authentification, mot de passe) entrent au journal de securite de
 * la console. Aucun mot de passe ni code n'y est jamais ecrit : seulement l'adresse visee ou le compte.
 *
 * Synchrone et sans effet sur la reponse : `SecurityJournal` protege chaque ecriture.
 */
class RecordAccessEvent
{
    public function handleLogin(Login $event): void
    {
        SecurityJournal::access(SecurityEvent::LoginSucceeded, $event->user->email ?? null, (int) $event->user->getAuthIdentifier());
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        $userId = $event->user?->getAuthIdentifier();

        SecurityJournal::access(SecurityEvent::LoginFailed, is_string($email) ? mb_strtolower($email) : null, $userId === null ? null : (int) $userId);
    }

    public function handleLogout(Logout $event): void
    {
        // Une session expiree n'a plus d'utilisateur a nommer : rien a retracer.
        if ($event->user === null) {
            return;
        }

        SecurityJournal::access(SecurityEvent::Logout, $event->user->email ?? null, (int) $event->user->getAuthIdentifier());
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        SecurityJournal::access(SecurityEvent::TwoFactorEnabled, $event->user->email ?? null, (int) $event->user->getAuthIdentifier());
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        SecurityJournal::access(SecurityEvent::TwoFactorDisabled, $event->user->email ?? null, (int) $event->user->getAuthIdentifier());
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        SecurityJournal::access(SecurityEvent::TwoFactorFailed, $event->user->email ?? null, (int) $event->user->getAuthIdentifier());
    }

    public function handlePasswordChanged(PasswordUpdatedViaController|PasswordReset $event): void
    {
        SecurityJournal::access(SecurityEvent::PasswordChanged, $event->user->email ?? null, (int) $event->user->getAuthIdentifier());
    }
}
