<?php

namespace App\Enums;

/**
 * Ce qu'un plan ouvre en plus des quotas (README section 3). Catalogue ferme, comme
 * `TenantPermission` : chaque entree correspond a une colonne de `plans` et a un point de controle
 * reel.
 */
enum PlanFeature: string
{
    case Reconciliation = 'reconciliation';
    case Reports = 'reports';
    case CustomDomain = 'custom_domain';
    case Sso = 'sso';

    /**
     * Get the `plans` column holding this feature.
     */
    public function column(): string
    {
        return "has_{$this->value}";
    }
}
