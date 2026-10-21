<?php

namespace App\Actions\Console;

use App\Models\User;
use App\Settings\ProtectionSettings;
use App\Support\Console\ConsoleJournal;

/**
 * Ouvrir ou fermer l'interrupteur « une seule session par compte ». Il ne ferme pas les sessions deja
 * ouvertes : il joue a la connexion suivante, pour tous les comptes. L'avant et l'apres vont au journal
 * central, le fermer desserre une protection.
 */
class UpdateSingleSession
{
    public function __construct(private ProtectionSettings $settings)
    {
        //
    }

    public function handle(bool $singleSession, User $actor): void
    {
        $before = $this->settings->single_session;

        if ($singleSession === $before) {
            return;
        }

        $this->settings->single_session = $singleSession;
        $this->settings->save();

        ConsoleJournal::record('single_session_updated', $actor, null, [
            'old' => ['single_session' => $before],
            'attributes' => ['single_session' => $singleSession],
        ]);
    }
}
