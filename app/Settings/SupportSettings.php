<?php

namespace App\Settings;

use App\Models\SupportAccessGrant;
use Spatie\LaravelSettings\Settings;

/**
 * Les durees qu'une organisation peut choisir pour un acces de support (README ecran 25), en
 * heures, de la plus courte a la plus longue. Reglees depuis l'ecran Securite de la console
 * (decision du proprietaire du projet, 2026-10-02) ; au depart 1, 4, 12 et 24 heures
 * (`SupportAccessGrant::DurationsInHours`).
 *
 * La plus longue est aussi le plafond d'une prolongation : un acces n'a jamais plus que cette duree
 * devant lui sans un nouveau geste d'un Proprietaire.
 */
class SupportSettings extends Settings
{
    /**
     * Plafond qu'aucun reglage ne depasse : au-dela de trois jours, ce n'est plus un acces
     * temporaire.
     */
    public const MaxHours = 72;

    /**
     * @var array<int, int>
     */
    public array $durations;

    public static function group(): string
    {
        return 'support';
    }

    /**
     * Get the longest duration offered. Sans aucune duree reglee (ce que la console refuse), on
     * retombe sur la plus longue du depart plutot que de laisser un acces sans plafond.
     */
    public function maxHours(): int
    {
        return $this->durations === [] ? max(SupportAccessGrant::DurationsInHours) : max($this->durations);
    }
}
