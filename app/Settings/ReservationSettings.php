<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Les bornes de la duree de reservation qu'un organisateur choisit pour son evenement, en minutes
 * (decision du proprietaire du projet, 2026-10-07). Reglees depuis l'ecran Securite de la console :
 * trop courte, l'invite n'a pas le temps de verser ; trop longue, des places restent bloquees par
 * des inscriptions qui ne paieront pas.
 */
class ReservationSettings extends Settings
{
    /**
     * Les limites qu'aucun reglage ne depasse : une minute au moins, une journee au plus.
     */
    public const Floor = 1;

    public const Ceiling = 1440;

    public int $hold_min_minutes;

    public int $hold_max_minutes;

    public static function group(): string
    {
        return 'reservation';
    }

    /**
     * Bring a duration back between the bounds : la valeur proposee par defaut, ou celle d'un
     * evenement enregistre avant un resserrement des bornes.
     */
    public function clamp(int $minutes): int
    {
        return min(max($minutes, $this->hold_min_minutes), $this->hold_max_minutes);
    }
}
