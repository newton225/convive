<?php

namespace App\Enums;

/**
 * Sort du paiement d'une inscription validee puis annulee (README 2.11). Nul tant que
 * l'inscription n'a rien encaisse : une inscription annulee avant validation n'a aucun paiement
 * a traiter.
 *
 * `Due` est le choix par defaut parce que c'est le seul qui n'efface rien : tant que personne n'a
 * tranche, la somme reste due et visible. Seule transition apres coup : `Due` vers `Refunded`.
 */
enum RefundStatus: string
{
    case Due = 'due';
    case Refunded = 'refunded';
    case Kept = 'kept';

    /**
     * Get the label shown in the back-office.
     */
    public function label(): string
    {
        return __("registrations.refund.statuses.{$this->value}");
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
