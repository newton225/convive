<?php

namespace App\Support;

use App\Enums\RefundStatus;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Les totaux de toute l'organisation pour la tresorerie (decision du 2026-10-16) : par evenement, par
 * compte de versement, et les remboursements a faire. Rien n'est stocke : tout se recalcule a
 * l'ouverture, comme le rapport d'un evenement, dont il reprend les regles (`Event::paymentTotals()`)
 * pour qu'aucun total ne diverge de celui de l'evenement.
 *
 * Le filtre de periode porte sur la date de l'evenement (son debut), pas sur la date de chaque
 * paiement : la tresorerie boucle un evenement apres l'autre.
 */
final class TreasuryOverview
{
    /** Ce que la liste des remboursements montre au plus : au-dela, la base d'inscrits fait foi. */
    public const RefundListLimit = 50;

    /**
     * @return array{
     *     totals: array{collected: int, refunded: int, fees: int, due: int, net: int},
     *     events: array<int, array{id: int, name: string, startsAt: string|null, collected: int, refunded: int, fees: int, due: int, net: int, registrationsUrl: string}>,
     *     accounts: array<int, array{label: string, channelLabel: string|null, collected: int, refunded: int, due: int}>,
     *     refundsDue: array<int, array{id: int, reference: string|null, name: string, eventName: string, amount: int, cancelledAt: string|null, registrationsUrl: string}>,
     *     refundsDueCount: int
     * }
     */
    public static function for(Tenant $tenant, ?Carbon $from, ?Carbon $to, ?int $refundLimit = self::RefundListLimit): array
    {
        $events = Event::query()
            ->when($from !== null, fn ($query) => $query->where('starts_at', '>=', $from->copy()->startOfDay()))
            ->when($to !== null, fn ($query) => $query->where('starts_at', '<=', $to->copy()->endOfDay()))
            ->orderByDesc('starts_at')
            ->get();

        $rows = $events->map(function (Event $event) use ($tenant) {
            $totals = $event->paymentTotals();

            return [
                'id' => $event->id,
                'name' => $event->name,
                'startsAt' => $event->starts_at?->toISOString(),
                'collected' => $totals['collected'],
                'refunded' => $totals['refunded'],
                'fees' => $totals['fees'],
                'due' => $totals['due'],
                'net' => $totals['net'],
                'registrationsUrl' => route('tenants.events.registrations.index', [$tenant, $event], absolute: false),
            ];
        })->values();

        return [
            'totals' => [
                'collected' => (int) $rows->sum('collected'),
                'refunded' => (int) $rows->sum('refunded'),
                'fees' => (int) $rows->sum('fees'),
                'due' => (int) $rows->sum('due'),
                'net' => (int) $rows->sum('net'),
            ],
            'events' => $rows->all(),
            'accounts' => self::accounts($events->pluck('id')->all()),
            ...self::refundsDue($tenant, $events, $refundLimit),
        ];
    }

    /**
     * L'argent de chaque compte de versement : ce que ses preuves validees ont apporte, ce qui en est
     * rendu et ce qui reste a rendre. Un montant se range sous le compte de la preuve du dossier.
     *
     * @param  array<int, int>  $eventIds
     * @return array<int, array{label: string, channelLabel: string|null, collected: int, refunded: int, due: int}>
     */
    private static function accounts(array $eventIds): array
    {
        $registrations = Registration::query()
            ->whereIn('event_id', $eventIds)
            ->where(fn ($query) => $query
                ->where('status', RegistrationStatus::Confirmed)
                ->orWhereNotNull('refund_status'))
            ->where('amount_due', '>', 0)
            ->with('latestProof.paymentAccount')
            ->get();

        return $registrations
            ->groupBy(fn (Registration $registration) => $registration->latestProof?->paymentAccount?->id ?? 0)
            ->map(function ($group) {
                $account = $group->first()->latestProof?->paymentAccount;

                return [
                    'label' => $account?->label ?? __('treasury.accounts.unknown'),
                    'channelLabel' => $account?->channel?->label(),
                    'collected' => (int) $group->sum('amount_due'),
                    'refunded' => (int) $group->where('refund_status', RefundStatus::Refunded)->sum('amount_due'),
                    'due' => (int) $group->where('refund_status', RefundStatus::Due)->sum('amount_due'),
                ];
            })
            ->sortByDesc('collected')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Event>  $events
     *                                          `$limit` nul : toute la liste, pour l'export (l'ecran s'arrete a `RefundListLimit`).
     * @return array{refundsDue: array<int, array<string, mixed>>, refundsDueCount: int}
     */
    private static function refundsDue(Tenant $tenant, $events, ?int $limit): array
    {
        $query = Registration::query()
            ->whereIn('event_id', $events->pluck('id'))
            ->where('refund_status', RefundStatus::Due);

        $names = $events->keyBy('id');

        return [
            'refundsDueCount' => (clone $query)->count(),
            'refundsDue' => $query
                ->orderBy('cancelled_at')
                ->when($limit !== null, fn ($query) => $query->limit($limit))
                ->get()
                ->map(fn (Registration $registration) => [
                    'id' => $registration->id,
                    'reference' => $registration->reference,
                    'name' => $registration->name,
                    'eventName' => $names[$registration->event_id]->name,
                    'amount' => $registration->amount_due,
                    'cancelledAt' => $registration->cancelled_at?->toISOString(),
                    'registrationsUrl' => route('tenants.events.registrations.index', [$tenant, $registration->event_id], absolute: false),
                ])
                ->all(),
        ];
    }
}
