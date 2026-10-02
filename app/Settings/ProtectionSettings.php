<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Les limites de protection que l'editeur regle depuis la console, ecran Securite.
 *
 * Aujourd'hui une seule : le nombre d'exports et d'imports de releve qu'une meme personne peut
 * lancer par heure (SECURITY.md M3, exfiltration par export legitime). Trente par defaut
 * (`AppServiceProvider::ExportsPerHour`) : cinq bloquait un tresorier qui exporte plusieurs
 * evenements le meme jour. Reglable sur decision du proprietaire du projet (2026-10-02).
 */
class ProtectionSettings extends Settings
{
    public int $exports_per_hour;

    public static function group(): string
    {
        return 'protection';
    }
}
