<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Les reglages de la periode d'essai (`App\Settings\TrialSettings`), avec leurs valeurs de depart :
 * celles de `config('convive.trial')`. La console les regle ensuite ; la configuration ne sert plus
 * qu'a cette premiere pose.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $days = config('convive.trial.days');

        $this->migrator->add('trial.enabled', (bool) config('convive.trial.enabled'));
        $this->migrator->add('trial.days', is_numeric($days) ? (int) $days : null);
        $this->migrator->add('trial.plan', (string) config('convive.trial.plan'));
    }

    public function down(): void
    {
        $this->migrator->delete('trial.enabled');
        $this->migrator->delete('trial.days');
        $this->migrator->delete('trial.plan');
    }
};
