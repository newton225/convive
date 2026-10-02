<?php

namespace App\Actions\Console;

use App\Models\User;
use App\Settings\SupportSettings;
use App\Support\Console\ConsoleJournal;

/**
 * Regler les durees qu'une organisation peut choisir pour un acces de support (README ecran 25).
 * Sans effet sur les acces deja ouverts. L'avant et l'apres vont au journal central : allonger ces
 * durees, c'est laisser l'equipe Convive lire plus longtemps.
 */
class UpdateSupportDurations
{
    public function __construct(private SupportSettings $settings)
    {
        //
    }

    /**
     * @param  array<int, int>  $durations
     */
    public function handle(array $durations, User $actor): void
    {
        $durations = array_values(array_unique($durations));
        sort($durations);

        $before = $this->settings->durations;

        if ($durations === $before) {
            return;
        }

        $this->settings->durations = $durations;
        $this->settings->save();

        ConsoleJournal::record('support_durations_updated', $actor, null, [
            'old' => ['durations' => $before],
            'attributes' => ['durations' => $durations],
        ]);
    }
}
