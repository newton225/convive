<?php

namespace App\Enums;

/**
 * Les trois variantes de mise en forme du billet (README ecran 15). Catalogue ferme, comme
 * `BrandFile` : un choix de presentation, jamais une saisie libre.
 */
enum TicketModel: string
{
    case Classic = 'classic';
    case Sober = 'sober';
    case Elegant = 'elegant';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $model) => $model->value, self::cases());
    }
}
