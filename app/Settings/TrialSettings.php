<?php

namespace App\Settings;

use App\Enums\PlanCode;
use Spatie\LaravelSettings\Settings;

/**
 * La periode d'essai offerte a toute organisation neuve (README section 3), reglee depuis la
 * console (decision du proprietaire du projet, 2026-10-02 : trente jours, et tout reste
 * parametrable, jusqu'a un essai sans fin).
 *
 * La duree ne vaut que pour les organisations ouvertes ensuite : la date de fin d'une organisation
 * deja ouverte est la sienne, et se change sur sa fiche. Fermer l'essai ou changer le plan offert
 * s'applique en revanche tout de suite a toutes les organisations a l'essai (`Tenant::isOnTrial()`,
 * `Tenant::plan()`). Les valeurs de depart viennent de `config('convive.trial')`, posees par la
 * migration des reglages.
 */
class TrialSettings extends Settings
{
    /**
     * Faux : aucune organisation n'est a l'essai, et une organisation neuve n'en commence pas.
     */
    public bool $enabled;

    /**
     * Duree de l'essai en jours ; nulle, l'essai n'a pas de date de fin.
     */
    public ?int $days;

    /**
     * Code du plan dont profite une organisation pendant son essai (`PlanCode`).
     */
    public string $plan;

    public static function group(): string
    {
        return 'trial';
    }

    public function planCode(): PlanCode
    {
        return PlanCode::tryFrom($this->plan) ?? PlanCode::default();
    }
}
