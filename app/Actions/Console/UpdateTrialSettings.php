<?php

namespace App\Actions\Console;

use App\Models\User;
use App\Settings\TrialSettings;
use App\Support\Console\ConsoleJournal;

/**
 * Regler la periode d'essai offerte aux organisations neuves (README section 3) : ouverte ou non,
 * sa duree (nulle : sans fin) et le plan dont elles profitent pendant ce temps. Sans effet sur les
 * organisations deja ouvertes. L'avant et l'apres vont au journal central.
 */
class UpdateTrialSettings
{
    public function __construct(private TrialSettings $settings)
    {
        //
    }

    /**
     * @param  array{enabled: bool, days: int|null, plan: string}  $attributes
     */
    public function handle(array $attributes, User $actor): void
    {
        $before = $this->current();

        if ($attributes === $before) {
            return;
        }

        $this->settings->enabled = $attributes['enabled'];
        $this->settings->days = $attributes['days'];
        $this->settings->plan = $attributes['plan'];
        $this->settings->save();

        ConsoleJournal::record('trial_settings_updated', $actor, null, [
            'old' => $before,
            'attributes' => $this->current(),
        ]);
    }

    /**
     * @return array{enabled: bool, days: int|null, plan: string}
     */
    public function current(): array
    {
        return [
            'enabled' => $this->settings->enabled,
            'days' => $this->settings->days,
            'plan' => $this->settings->plan,
        ];
    }
}
