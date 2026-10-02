<?php

use App\Models\SupportAccessGrant;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Les durees proposees pour un acces de support (`App\Settings\SupportSettings`), avec leurs valeurs
 * de depart. La console les regle ensuite.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('support.durations', SupportAccessGrant::DurationsInHours);
    }

    public function down(): void
    {
        $this->migrator->delete('support.durations');
    }
};
