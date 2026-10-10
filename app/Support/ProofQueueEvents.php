<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Models\Event;

/**
 * Les evenements de l'organisation courante qui ont des preuves a verifier, avec leur nombre.
 *
 * Meme definition que la file de preuves : une inscription « preuve envoyee » qui porte au moins
 * une preuve. Sert au bouton du tableau de bord (le total) et au selecteur de la file.
 */
class ProofQueueEvents
{
    /**
     * Les evenements ayant des preuves a verifier, le plus proche d'abord. `$alwaysInclude` y
     * ajoute l'evenement regarde, meme sans preuve, pour que le selecteur le montre.
     * `$onlyEventId` borne la liste a un seul evenement (acces du support limite).
     *
     * @return array<int, array{id: int, name: string, proofsToCheck: int}>
     */
    public static function all(?Event $alwaysInclude = null, ?int $onlyEventId = null): array
    {
        return Event::query()
            ->withCount([
                'registrations as proofs_to_check' => fn ($registrations) => $registrations
                    ->where('status', RegistrationStatus::ProofSubmitted)
                    ->whereHas('proofs'),
            ])
            ->when($onlyEventId !== null, fn ($query) => $query->whereKey($onlyEventId))
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Event $event) => $event->proofs_to_check > 0 || $event->is($alwaysInclude))
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'proofsToCheck' => (int) $event->proofs_to_check,
            ])
            ->values()
            ->all();
    }

    /**
     * Le total annonce par le bouton du tableau de bord, et l'evenement ou il mene : celui que la
     * page resume s'il a des preuves, sinon le premier qui en a.
     *
     * @return array{total: int, eventId: int|null}
     */
    public static function summary(?int $displayedEventId): array
    {
        $events = collect(self::all())->where('proofsToCheck', '>', 0);

        return [
            'total' => (int) $events->sum('proofsToCheck'),
            'eventId' => $events->firstWhere('id', $displayedEventId)['id'] ?? $events->first()['id'] ?? null,
        ];
    }
}
