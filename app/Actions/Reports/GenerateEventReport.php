<?php

namespace App\Actions\Reports;

use App\Enums\RegistrationStatus;
use App\Enums\ScanResult;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ScanEvent;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Le rapport post-evenement (README ecran 22), etape 9 de « Ordre de construction » : presence,
 * absents, recettes, duree moyenne de controle, presence et recettes par unite.
 *
 * Trois choix a connaitre, faute de colonne ou de mesure pour faire mieux :
 * - Tout se compte sur les inscriptions confirmees : c'est ce que l'organisation a verifie avoir
 *   recu. Une inscription annulee n'est ni presente ni absente.
 * - « Present » signifie que le billet du dossier a ete accepte une fois (`TicketArrival`) : un
 *   seul billet par dossier, tout le groupe est compte present ensemble.
 * - La ventilation suit l'unite du participant principal (`registration.unit_id`), jamais celle de
 *   chaque accompagnateur.
 */
class GenerateEventReport
{
    /**
     * Build the report of the given event.
     *
     * @return array{
     *     confirmedRegistrations: int,
     *     confirmedSeats: int,
     *     presentRegistrations: int,
     *     presentSeats: int,
     *     absentRegistrations: int,
     *     absentSeats: int,
     *     collectedAmount: int,
     *     averageScanIntervalSeconds: int|null,
     *     units: array<int, array{unit: string, confirmedRegistrations: int, presentRegistrations: int, presentSeats: int, collectedAmount: int}>
     * }
     */
    public function handle(Event $event): array
    {
        $confirmed = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->with(['unit', 'tickets.arrival'])
            ->get();

        // Un billet par personne (README 2.8) : une inscription est presente des qu'une personne
        // du groupe est entree, et les places presentes comptent les personnes reellement entrees,
        // pas la taille du groupe.
        $present = $confirmed->filter(fn (Registration $registration) => $this->arrivals($registration) > 0);
        $absent = $confirmed->diff($present);
        $confirmedSeats = (int) $confirmed->sum('party_size');
        $presentSeats = (int) $present->sum(fn (Registration $registration) => $this->arrivals($registration));

        return [
            'confirmedRegistrations' => $confirmed->count(),
            'confirmedSeats' => $confirmedSeats,
            'presentRegistrations' => $present->count(),
            'presentSeats' => $presentSeats,
            'absentRegistrations' => $absent->count(),
            'absentSeats' => max(0, $confirmedSeats - $presentSeats),
            'collectedAmount' => (int) $confirmed->sum('amount_due'),
            'averageScanIntervalSeconds' => $this->averageScanIntervalSeconds($event),
            'units' => $this->byUnit($confirmed, $present),
        ];
    }

    /**
     * Count the people of the registration's group who have entered.
     */
    private function arrivals(Registration $registration): int
    {
        return $registration->tickets->filter(fn (Ticket $ticket) => $ticket->arrival !== null)->count();
    }

    /**
     * Get the mean gap between consecutive accepted scans, in seconds, or null under two scans.
     *
     * Un indicateur de debit au poste de controle, pas la duree d'un controle individuel : rien
     * ne mesure le debut et la fin d'un scan. Les refus et les billets deja scannes n'entrent pas
     * dans le calcul, ils ne font pas avancer la file. La moyenne des ecarts consecutifs vaut
     * l'ecart total divise par le nombre d'intervalles.
     */
    private function averageScanIntervalSeconds(Event $event): ?int
    {
        $timestamps = ScanEvent::where('event_id', $event->id)
            ->where('result', ScanResult::Accepted)
            ->orderBy('created_at')
            ->get(['created_at'])
            ->map(fn (ScanEvent $scan) => $scan->created_at->getTimestamp());

        if ($timestamps->count() < 2) {
            return null;
        }

        return (int) round(($timestamps->last() - $timestamps->first()) / ($timestamps->count() - 1));
    }

    /**
     * @param  Collection<int, Registration>  $confirmed
     * @param  Collection<int, Registration>  $present
     * @return array<int, array{unit: string, confirmedRegistrations: int, presentRegistrations: int, presentSeats: int, collectedAmount: int}>
     */
    private function byUnit(Collection $confirmed, Collection $present): array
    {
        return $confirmed
            ->groupBy('unit_id')
            ->map(function (Collection $registrations) use ($present) {
                $unit = $registrations->first()->unit;
                $presentHere = $registrations->filter(fn (Registration $registration) => $present->contains($registration));

                return [
                    'position' => $unit->position,
                    'unit' => $unit->name,
                    'confirmedRegistrations' => $registrations->count(),
                    'presentRegistrations' => $presentHere->count(),
                    'presentSeats' => (int) $presentHere->sum(fn (Registration $registration) => $this->arrivals($registration)),
                    'collectedAmount' => (int) $registrations->sum('amount_due'),
                ];
            })
            ->sortBy([['position', 'asc'], ['unit', 'asc']])
            ->map(fn (array $row) => [
                'unit' => $row['unit'],
                'confirmedRegistrations' => $row['confirmedRegistrations'],
                'presentRegistrations' => $row['presentRegistrations'],
                'presentSeats' => $row['presentSeats'],
                'collectedAmount' => $row['collectedAmount'],
            ])
            ->values()
            ->all();
    }
}
