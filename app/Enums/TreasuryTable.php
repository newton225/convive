<?php

namespace App\Enums;

/**
 * Les trois tableaux de l'ecran Tresorerie qui s'exportent en Excel ou CSV. La valeur est le segment
 * d'adresse (`treasury/export/{table}/{format}`), `filename()` le nom du fichier telecharge.
 */
enum TreasuryTable: string
{
    case Events = 'events';
    case Accounts = 'accounts';
    case Refunds = 'refunds';

    /**
     * Le nom du fichier, sans extension.
     */
    public function filename(): string
    {
        return 'tresorerie-'.match ($this) {
            self::Events => 'evenements',
            self::Accounts => 'comptes',
            self::Refunds => 'remboursements',
        };
    }
}
