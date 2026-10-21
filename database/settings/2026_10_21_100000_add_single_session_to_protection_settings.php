<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * L'interrupteur « une seule session par compte » (`App\Settings\ProtectionSettings`), ferme au
 * depart : l'editeur l'active depuis l'ecran Securite de la console.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('protection.single_session', false);
    }

    public function down(): void
    {
        $this->migrator->delete('protection.single_session');
    }
};
