<?php

namespace App\Enums;

/**
 * Statuts reellement stockes d'un evenement.
 *
 * « Complet » n'en fait pas partie : c'est un etat calcule a partir des places restantes. Un
 * statut « complet » stocke devrait etre mis a jour par quelqu'un, et ce quelqu'un finirait
 * par se tromper au pire moment.
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Ongoing = 'ongoing';
    case Closed = 'closed';

    public function label(): string
    {
        return __("events.statuses.{$this->value}");
    }

    /**
     * Determine whether guests may register at all in this status.
     */
    public function acceptsRegistrations(): bool
    {
        return $this === self::Open;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
