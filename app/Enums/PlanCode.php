<?php

namespace App\Enums;

/**
 * Les trois plans du produit (README section 3) et leurs quotas. Cette enum est la source des
 * valeurs de depart : `App\Models\Plan::ensure()` en tire la ligne en base a la premiere
 * utilisation, puis n'y touche plus, pour que l'exploitant puisse ajuster une ligne sans qu'un
 * deploiement l'ecrase.
 *
 * Un plafond `null` veut dire illimite.
 *
 * Prix mensuels arretes par le proprietaire du produit le 2026-09-27, repris du prototype
 * (Convive.dc.html) : Essentiel gratuit sans limite de duree tant que la politique ne change pas,
 * Association 45 000 F CFA, Institution sur devis. Francs CFA sans decimale ; euro et dollar en
 * centimes (6900 = 69,00). L'euro suit la parite fixe du franc CFA (655,957) ; le dollar reste a
 * confirmer par le proprietaire.
 *
 * Domaine personnalise et SSO : colonnes conservees pour le jour ou ils seront construits, mais
 * annonces nulle part tant qu'ils n'existent pas (decision du 2026-09-27).
 */
enum PlanCode: string
{
    case Essential = 'essential';
    case Association = 'association';
    case Institution = 'institution';

    /**
     * Get the plan the organisations without a subscription run on.
     */
    public static function default(): self
    {
        return self::Essential;
    }

    /**
     * @return array{name: string, monthly_price: int|null, monthly_price_eur: int|null, monthly_price_usd: int|null, max_active_events: int|null, max_registrations: int|null, max_members: int|null, has_reconciliation: bool, has_reports: bool, has_custom_domain: bool, has_sso: bool, position: int}
     */
    public function definition(): array
    {
        return match ($this) {
            self::Essential => [
                'name' => 'Essentiel',
                'monthly_price' => 0,
                'monthly_price_eur' => 0,
                'monthly_price_usd' => 0,
                'max_active_events' => 1,
                'max_registrations' => 200,
                'max_members' => 2,
                'has_reconciliation' => false,
                'has_reports' => false,
                'has_custom_domain' => false,
                'has_sso' => false,
                'position' => 1,
            ],
            self::Association => [
                'name' => 'Association',
                'monthly_price' => 45000,
                'monthly_price_eur' => 6900,
                'monthly_price_usd' => 7900,
                'max_active_events' => 5,
                'max_registrations' => 1000,
                'max_members' => 10,
                'has_reconciliation' => true,
                'has_reports' => true,
                'has_custom_domain' => false,
                'has_sso' => false,
                'position' => 2,
            ],
            self::Institution => [
                'name' => 'Institution',
                'monthly_price' => null,
                'monthly_price_eur' => null,
                'monthly_price_usd' => null,
                'max_active_events' => null,
                'max_registrations' => null,
                'max_members' => null,
                'has_reconciliation' => true,
                'has_reports' => true,
                'has_custom_domain' => false,
                'has_sso' => false,
                'position' => 3,
            ],
        };
    }
}
