<?php

namespace App\Support;

use App\Enums\RefundStatus;
use App\Enums\RegistrationStatus;
use App\Enums\ScanResult;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\ScanEvent;
use App\Models\SeatingTable;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Le tableau de bord (README ecran 17) : inscrits, preuves validees, preuves a verifier, sans
 * preuve, places restantes, inscriptions par jour, preuves par canal, occupation des tables,
 * activite recente. Toujours pour un seul evenement a la fois : le plus proche encore ouvert,
 * sinon le plus recent (voir `forTenant()`).
 */
class DashboardOverview
{
    /**
     * Nombre de jours representes sur le graphique des inscriptions par jour.
     */
    public const RegistrationsPerDayWindow = 14;

    /**
     * Nombre d'entrees affichees dans l'activite recente.
     */
    public const RecentActivityLimit = 10;

    /**
     * Pick the event this dashboard summarises : the soonest one still open or ongoing, else
     * the most recently created one, else none.
     */
    public static function relevantEvent(): ?Event
    {
        return Event::whereIn('status', ['open', 'ongoing'])
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at')
            ->first()
            ?? Event::latest('created_at')->first();
    }

    /**
     * Get the event chosen in the dashboard's picker, or the automatic one when none (or one that
     * does not exist in this organisation) was chosen.
     *
     * La base lue est celle de l'organisation courante : l'identifiant d'un evenement d'une autre
     * organisation n'y designe rien, ou un autre evenement de celle-ci.
     */
    public static function chosenEvent(?int $eventId): ?Event
    {
        return ($eventId !== null ? Event::find($eventId) : null) ?? self::relevantEvent();
    }

    /**
     * Get the events the picker offers : ouverts et en cours d'abord, du plus proche au plus
     * lointain, puis les autres (brouillons, termines), du plus recent au plus ancien.
     *
     * @return array<int, array{id: int, name: string, startsAt: string|null}>
     */
    public static function choices(): array
    {
        $active = Event::whereIn('status', ['open', 'ongoing'])
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at')
            ->get();

        $others = Event::whereNotIn('status', ['open', 'ongoing'])
            ->latest('created_at')
            ->get();

        return $active->concat($others)
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'startsAt' => $event->starts_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(Event $event): array
    {
        return [
            'eventId' => $event->id,
            'eventName' => $event->name,
            'capacity' => $event->capacity(),
            'kpis' => self::kpis($event),
            'context' => self::context($event),
            'holdExpiry' => self::holdExpiry($event),
            'registrationsPerDay' => self::registrationsPerDay($event),
            'proofsByChannel' => self::proofsByChannel($event),
            'tableOccupancy' => self::tableOccupancy($event),
            'recentActivity' => self::recentActivity($event),
            'refundsDue' => self::refundsDue($event),
        ];
    }

    /**
     * @return array{registrations: int, validated: int, toCheck: int, withoutProof: int, seatsLeft: int}
     */
    private static function kpis(Event $event): array
    {
        return [
            'registrations' => $event->registrations()->count(),
            'validated' => $event->registrations()->where('status', RegistrationStatus::Confirmed)->count(),
            'toCheck' => $event->registrations()->where('status', RegistrationStatus::ProofSubmitted)->whereHas('proofs')->count(),
            'withoutProof' => $event->registrations()->whereIn('status', Registration::UnfinalizedStatuses)->count(),
            'seatsLeft' => $event->remainingSeats(),
        ];
    }

    /**
     * Ce qui donne du sens aux chiffres cles (prototype Convive.dc.html, tableau de bord) : la
     * tendance de la semaine, l'argent deja encaisse, les preuves qui attendent depuis trop
     * longtemps, la prochaine purge et le compte a rebours jusqu'a l'evenement.
     *
     * @return array{registrationsThisWeek: int, collectedAmount: int, validatedShare: int|null, waitingOver24h: int, purgeAt: string|null, daysUntilEvent: int|null}
     */
    private static function context(Event $event): array
    {
        $registrations = $event->registrations()->count();
        $validated = $event->registrations()->where('status', RegistrationStatus::Confirmed)->count();

        return [
            'registrationsThisWeek' => $event->registrations()->where('created_at', '>=', now()->subDays(7))->count(),
            'collectedAmount' => $event->collectedAmount(),
            'validatedShare' => $registrations > 0 ? (int) round(100 * $validated / $registrations) : null,
            'waitingOver24h' => $event->registrations()
                ->where('status', RegistrationStatus::ProofSubmitted)
                ->whereHas('latestProof', fn ($query) => $query->where('created_at', '<', now()->subDay()))
                ->count(),
            'purgeAt' => $event->purge_at?->isFuture() ? $event->purge_at->toISOString() : null,
            'daysUntilEvent' => $event->starts_at?->isFuture()
                ? (int) now()->startOfDay()->diffInDays($event->starts_at->startOfDay())
                : null,
        ];
    }

    /**
     * Cancelled registrations whose payment is still owed back to the guest (README 2.11) : le
     * tableau de bord le signale tant qu'il en reste, sinon rien.
     *
     * @return array{count: int, amount: int}|null
     */
    private static function refundsDue(Event $event): ?array
    {
        $due = $event->registrations()->where('refund_status', RefundStatus::Due);
        $count = $due->count();

        return $count === 0 ? null : [
            'count' => $count,
            'amount' => (int) $due->sum('amount_due'),
        ];
    }

    /**
     * Share of reservations that lapsed without a proof (SECURITY.md C3) : a sudden high rate is
     * what an automated seat-blocking attack looks like from the organiser's side.
     *
     * Une relance apres expiration compte comme une reservation de plus et une expiration de plus
     * (`lapsed_holds_count`) : sinon l'inscription relancee sans cesse passerait pour une seule.
     *
     * @return array{lapsed: int, holds: int, rate: int|null}
     */
    private static function holdExpiry(Event $event): array
    {
        $relaunched = (int) $event->registrations()->sum('lapsed_holds_count');
        $lapsed = $event->registrations()->where('status', RegistrationStatus::Expired)->count() + $relaunched;
        $holds = $event->registrations()->whereNotNull('hold_sequence')->count() + $relaunched;

        return [
            'lapsed' => $lapsed,
            'holds' => $holds,
            'rate' => $holds > 0 ? (int) round(100 * $lapsed / $holds) : null,
        ];
    }

    /**
     * @return array<int, array{date: string, count: int}>
     */
    private static function registrationsPerDay(Event $event): array
    {
        $since = Carbon::today()->subDays(self::RegistrationsPerDayWindow - 1);

        $counted = $event->registrations()
            ->where('created_at', '>=', $since)
            ->get(['created_at'])
            ->countBy(fn (Registration $registration) => $registration->created_at->toDateString());

        return collect(range(0, self::RegistrationsPerDayWindow - 1))
            ->map(function (int $offset) use ($since, $counted) {
                $date = $since->copy()->addDays($offset)->toDateString();

                return ['date' => $date, 'count' => $counted->get($date, 0)];
            })
            ->all();
    }

    /**
     * @return array<int, array{channel: string, count: int}>
     */
    private static function proofsByChannel(Event $event): array
    {
        return PaymentProof::whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
            ->get(['channel'])
            ->countBy(fn (PaymentProof $proof) => $proof->channel->label())
            ->map(fn (int $count, string $channel) => ['channel' => $channel, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{number: int, seated: int, capacity: int}>
     */
    private static function tableOccupancy(Event $event): array
    {
        return SeatingTable::where('event_id', $event->id)
            ->with('assignments.registration')
            ->ordered()
            ->get()
            ->map(fn (SeatingTable $table) => [
                'number' => $table->number,
                'seated' => $table->capacity - $table->remainingCapacity(),
                'capacity' => $table->capacity,
            ])
            ->all();
    }

    /**
     * Roll up the several sources an activity can come from into one chronological feed.
     * `activity_log` only carries two of the six kinds the dashboard shows (validated, rejected,
     * cancelled) : the others come straight from the tables that hold them.
     *
     * @return array<int, array{id: string, type: string, name: string|null, at: string}>
     */
    private static function recentActivity(Event $event): array
    {
        $proofsReceived = PaymentProof::whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
            ->with('registration')
            ->latest('created_at')
            ->limit(self::RecentActivityLimit)
            ->get()
            ->map(fn (PaymentProof $proof) => [
                'id' => "proof-received-{$proof->id}",
                'type' => 'proof_received',
                'name' => $proof->registration->name,
                'at' => $proof->created_at,
            ]);

        $proofDecisions = Activity::whereIn('description', ['proofs.validated', 'proofs.rejected'])
            ->whereHasMorph('subject', [PaymentProof::class], fn ($query) => $query
                ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id)))
            ->with('subject.registration')
            ->latest('created_at')
            ->limit(self::RecentActivityLimit)
            ->get()
            ->map(function (Activity $activity) {
                $proof = $activity->subject;

                return [
                    'id' => "proof-decision-{$activity->id}",
                    'type' => $activity->description === 'proofs.validated' ? 'proof_approved' : 'proof_rejected',
                    'name' => $proof instanceof PaymentProof ? $proof->registration->name : null,
                    'at' => $activity->created_at,
                ];
            });

        $cancellations = Activity::where('description', 'registrations.cancelled')
            ->whereHasMorph('subject', [Registration::class], fn ($query) => $query->where('event_id', $event->id))
            ->with('subject')
            ->latest('created_at')
            ->limit(self::RecentActivityLimit)
            ->get()
            ->map(function (Activity $activity) {
                $registration = $activity->subject;

                return [
                    'id' => "cancellation-{$activity->id}",
                    'type' => 'registration_cancelled',
                    'name' => $registration instanceof Registration ? $registration->name : null,
                    'at' => $activity->created_at,
                ];
            });

        $expirations = $event->registrations()
            ->where('status', RegistrationStatus::Expired)
            ->latest('updated_at')
            ->limit(self::RecentActivityLimit)
            ->get()
            ->map(fn (Registration $registration) => [
                'id' => "expiration-{$registration->id}",
                'type' => 'hold_expired',
                'name' => $registration->name,
                'at' => $registration->updated_at,
            ]);

        $refusedScans = ScanEvent::where('event_id', $event->id)
            ->where('result', ScanResult::Refused)
            ->latest('created_at')
            ->limit(self::RecentActivityLimit)
            ->get()
            ->map(fn (ScanEvent $scan) => [
                'id' => "scan-{$scan->id}",
                'type' => 'scan_refused',
                'name' => null,
                'at' => $scan->created_at,
            ]);

        return $proofsReceived
            ->concat($proofDecisions)
            ->concat($cancellations)
            ->concat($expirations)
            ->concat($refusedScans)
            ->filter(fn (array $entry) => $entry['at'] !== null)
            ->sortByDesc(fn (array $entry) => $entry['at'])
            ->take(self::RecentActivityLimit)
            ->values()
            ->map(fn (array $entry) => [
                ...$entry,
                'at' => $entry['at']->toISOString(),
            ])
            ->all();
    }
}
