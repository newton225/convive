<?php

namespace App\Listeners;

use App\Models\SecurityEvent;
use App\Settings\ProtectionSettings;
use App\Support\Console\SecurityJournal;
use Illuminate\Auth\Events\Login;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\DB;

/**
 * Une seule session par compte (decision du 2026-10-21, interrupteur de l'ecran Securite de la
 * console) : quand il est ouvert, la connexion qui vient d'aboutir ferme les autres sessions du meme
 * compte. L'ancien navigateur est renvoye a la connexion a sa requete suivante.
 *
 * Passe par la table des sessions : Laravel n'offre ce geste, `Auth::logoutOtherDevices()`, qu'avec le
 * mot de passe en clair, que la connexion par code ou par cle d'acces n'a pas. Seul le pilote
 * `database` est pris en charge, c'est celui de l'application.
 */
class EndOtherSessions
{
    public function __construct(private ProtectionSettings $settings)
    {
        //
    }

    public function handle(Login $event): void
    {
        if (! $this->settings->single_session || ! request()->hasSession()) {
            return;
        }

        if (! request()->session()->getHandler() instanceof DatabaseSessionHandler) {
            return;
        }

        $closed = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $event->user->getAuthIdentifier())
            ->where('id', '!=', request()->session()->getId())
            ->delete();

        if ($closed > 0) {
            SecurityJournal::access(
                SecurityEvent::SessionsEnded,
                ($event->user->email ?? '').' ('.$closed.')',
                (int) $event->user->getAuthIdentifier(),
            );
        }
    }
}
