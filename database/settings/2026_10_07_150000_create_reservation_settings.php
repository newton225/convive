<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Les bornes de la duree de reservation (`App\Settings\ReservationSettings`), avec leurs valeurs de
 * depart : de 5 minutes a une heure. La console les regle ensuite.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('reservation.hold_min_minutes', 5);
        $this->migrator->add('reservation.hold_max_minutes', 60);
    }

    public function down(): void
    {
        $this->migrator->delete('reservation.hold_min_minutes');
        $this->migrator->delete('reservation.hold_max_minutes');
    }
};
