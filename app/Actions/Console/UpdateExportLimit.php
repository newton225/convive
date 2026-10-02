<?php

namespace App\Actions\Console;

use App\Models\User;
use App\Settings\ProtectionSettings;
use App\Support\Console\ConsoleJournal;

/**
 * Regler le nombre d'exports qu'une meme personne peut lancer par heure (SECURITY.md M3). Prend
 * effet a la requete suivante, pour toutes les organisations. L'avant et l'apres vont au journal
 * central : relever cette limite, c'est desserrer une protection.
 */
class UpdateExportLimit
{
    public function __construct(private ProtectionSettings $settings)
    {
        //
    }

    public function handle(int $exportsPerHour, User $actor): void
    {
        $before = $this->settings->exports_per_hour;

        if ($exportsPerHour === $before) {
            return;
        }

        $this->settings->exports_per_hour = $exportsPerHour;
        $this->settings->save();

        ConsoleJournal::record('export_limit_updated', $actor, null, [
            'old' => ['exports_per_hour' => $before],
            'attributes' => ['exports_per_hour' => $exportsPerHour],
        ]);
    }
}
