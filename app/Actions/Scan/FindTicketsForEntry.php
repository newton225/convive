<?php

namespace App\Actions\Scan;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Search\UnaccentedSearch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche d'un invite a l'entree quand son QR ne peut pas etre lu (README ecran 26) : par la
 * reference de son dossier, ou par son nom. Ne rend que des billets qui peuvent ouvrir la porte de
 * cet evenement : ceux des inscriptions confirmees.
 */
class FindTicketsForEntry
{
    /**
     * En dessous, la recherche ne rend rien : une ou deux lettres donneraient la liste des invites
     * a qui tient le telephone de l'agent.
     */
    public const MinimumLength = 3;

    /**
     * L'agent cherche une personne precise, pas une liste : au-dela, il affine sa recherche.
     */
    public const Limit = 10;

    /**
     * @return array{
     *     search: string,
     *     tooShort: bool,
     *     minimumLength: int,
     *     limit: int,
     *     truncated: bool,
     *     tickets: array<int, array{
     *         id: int,
     *         name: string,
     *         unit: string,
     *         guestOf: string|null,
     *         reference: string|null,
     *         tableNumber: int|null,
     *         arrivedAt: string|null,
     *         arrivedBy: string|null,
     *     }>,
     * }
     */
    public function handle(Event $event, string $search): array
    {
        $search = trim($search);

        if (mb_strlen($search) < self::MinimumLength) {
            return [
                'search' => $search,
                'tooShort' => true,
                'minimumLength' => self::MinimumLength,
                'limit' => self::Limit,
                'truncated' => false,
                'tickets' => [],
            ];
        }

        $tickets = Ticket::query()
            ->whereHas('registration', fn (Builder $registration) => $registration
                ->where('event_id', $event->id)
                ->where('status', RegistrationStatus::Confirmed))
            ->where(fn (Builder $query) => $query
                // La reference designe le dossier : elle rend le billet de chaque personne du groupe.
                ->whereHas('registration', fn (Builder $registration) => UnaccentedSearch::apply($registration, ['reference'], $search))
                ->orWhere(fn (Builder $companion) => UnaccentedSearch::apply($companion, ['holder_name'], $search))
                // Le billet de l'invite principal ne porte pas de nom propre : c'est celui du dossier.
                ->orWhere(fn (Builder $main) => $main
                    ->where('holder_position', Ticket::GuestPosition)
                    ->whereHas('registration', fn (Builder $registration) => UnaccentedSearch::apply($registration, ['name'], $search))))
            ->with('registration.unit', 'registration.tableAssignment.seatingTable', 'holderUnit', 'arrival')
            ->orderBy('registration_id')
            ->orderBy('holder_position')
            ->limit(self::Limit + 1)
            ->get();

        $shown = $tickets->take(self::Limit);

        // `User` vit dans la base centrale : pas de relation possible depuis un passage.
        $agents = User::whereIn('id', $shown->pluck('arrival.performed_by_user_id')->filter()->unique())
            ->pluck('name', 'id');

        return [
            'search' => $search,
            'tooShort' => false,
            'minimumLength' => self::MinimumLength,
            'limit' => self::Limit,
            'truncated' => $tickets->count() > self::Limit,
            'tickets' => $shown->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'name' => $ticket->holderName(),
                'unit' => $ticket->holderUnitName(),
                'guestOf' => $ticket->isCompanion() ? $ticket->registration->name : null,
                'reference' => $ticket->registration->reference,
                'tableNumber' => $ticket->registration->tableAssignment?->seatingTable->number,
                'arrivedAt' => $ticket->arrival?->created_at?->toISOString(),
                'arrivedBy' => $ticket->arrival !== null ? $agents->get($ticket->arrival->performed_by_user_id) : null,
            ])->values()->all(),
        ];
    }
}
