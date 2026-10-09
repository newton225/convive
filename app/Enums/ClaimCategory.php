<?php

namespace App\Enums;

/**
 * Le sujet d'une reclamation d'invite : catalogue ferme, l'invite choisit dans une liste plutot que
 * d'ecrire un sujet libre, et l'organisation trie d'un coup d'oeil.
 */
enum ClaimCategory: string
{
    case Payment = 'payment';
    case Refund = 'refund';
    case Ticket = 'ticket';
    case Other = 'other';

    public function label(): string
    {
        return __("claims.categories.{$this->value}");
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category) => $category->value, self::cases());
    }
}
