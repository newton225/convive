<?php

use App\Providers\AppServiceProvider;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * La limite d'exports par heure (`App\Settings\ProtectionSettings`), avec sa valeur de depart. La
 * console la regle ensuite.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('protection.exports_per_hour', AppServiceProvider::ExportsPerHour);
    }

    public function down(): void
    {
        $this->migrator->delete('protection.exports_per_hour');
    }
};
