<?php

namespace App\Actions\Scan;

use App\Actions\Notifications\SendAlert;
use App\Enums\EventStatus;
use App\Enums\NotificationType;
use App\Enums\RegistrationStatus;
use App\Enums\ScanResult;
use App\Models\Event;
use App\Models\ScanEvent;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketArrival;
use App\Models\User;
use App\Support\TicketToken;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

/**
 * Verification d'un billet au scan (README 2.8, ecran 26), etape 7 de « Ordre de construction ».
 *
 * Trois resultats, jamais plus (README) : accepte, deja scanne, refuse. « Deja scanne » n'est
 * distingue de « refuse » qu'apres avoir su qu'il s'agit d'un billet reel : un jeton falsifie ou
 * expire est refuse, jamais annonce comme deja utilise (ce serait confirmer a un faussaire que
 * le format qu'il a essaye correspond a un billet existant).
 */
class ScanTicket
{
    /**
     * Verify the given QR token for the event, recording the attempt in the journal either way.
     *
     * `TicketArrival` porte la garantie contre deux scans concurrents acceptant tous les deux le
     * meme billet (contrainte d'unicite reelle sur `ticket_id`, CLAUDE.md, « Base de donnees ») :
     * pas de `Cache::lock()` ici, l'echec de l'ecriture EST le signal recherche, pas un accident
     * a retenter.
     *
     * @return array{
     *     result: ScanResult,
     *     forced: bool,
     *     registration: array{name: string, unit: string, partySize: int, guestOf: string|null, tableNumber: int|null}|null,
     *     firstScannedAt: CarbonInterface|null,
     *     firstScannedBy: string|null,
     * }
     */
    public function handle(Event $event, string $token, User $actor, bool $force = false, ?string $station = null): array
    {
        $ticket = $this->resolveTicket($event, $token);

        if ($ticket === null || ! $this->ticketIsUsable($event, $ticket)) {
            $this->journal($event, null, $actor, ScanResult::Refused, false, $station);

            return $this->outcome(ScanResult::Refused, false, null, null, null);
        }

        try {
            TicketArrival::create([
                'ticket_id' => $ticket->id,
                'performed_by_user_id' => $actor->id,
            ]);

            $this->journal($event, $ticket, $actor, ScanResult::Accepted, false, $station);

            return $this->outcome(ScanResult::Accepted, false, $ticket, null, null);
        } catch (QueryException) {
            $arrival = $ticket->arrival()->first();

            $this->journal($event, $ticket, $actor, ScanResult::AlreadyScanned, $force, $station);

            return $this->outcome(
                ScanResult::AlreadyScanned,
                $force,
                $force ? $ticket : null,
                $arrival?->created_at,
                $arrival !== null ? User::find($arrival->performed_by_user_id)?->name : null,
            );
        }
    }

    /**
     * Resolve the token to a real, previously issued ticket of this event, or null when the
     * signature, the tenant or the event do not match.
     */
    private function resolveTicket(Event $event, string $token): ?Ticket
    {
        if ($event->qr_public_key === null) {
            return null;
        }

        $payload = TicketToken::verify($token, $event->qr_public_key);

        if ($payload === null) {
            return null;
        }

        if (($payload['tenant_id'] ?? null) !== Tenant::current()?->id
            || ($payload['event_id'] ?? null) !== $event->id
            || ($payload['key_version'] ?? null) !== $event->qr_key_version
            || $this->expired($event, $payload['not_after'] ?? null)) {
            return null;
        }

        return Ticket::where('registration_id', $payload['registration_id'] ?? null)
            ->where('nonce', $payload['nonce'] ?? null)
            ->first();
    }

    /**
     * Determine whether the token's deadline has passed (SECURITY.md C2).
     *
     * La plus tardive des deux echeances fait foi : celle signee dans le jeton, et celle que la
     * date actuelle de l'evenement donnerait. Un billet telecharge avant un report garde sinon
     * l'ancienne echeance et serait refuse a la porte le nouveau jour.
     */
    private function expired(Event $event, mixed $notAfter): bool
    {
        $deadlines = array_filter([
            is_int($notAfter) ? $notAfter : null,
            $event->ticketValidUntil()?->getTimestamp(),
        ]);

        return $deadlines !== [] && now()->getTimestamp() > max($deadlines);
    }

    /**
     * Determine whether a resolved ticket may still grant entry : its event is not closed, its
     * registration is still confirmed (README 2.8).
     */
    private function ticketIsUsable(Event $event, Ticket $ticket): bool
    {
        return $event->status !== EventStatus::Closed
            && $ticket->registration->status === RegistrationStatus::Confirmed;
    }

    private function journal(Event $event, ?Ticket $ticket, User $actor, ScanResult $result, bool $forced, ?string $station): void
    {
        ScanEvent::create([
            'event_id' => $event->id,
            'ticket_id' => $ticket?->id,
            'performed_by_user_id' => $actor->id,
            'result' => $result,
            'forced' => $forced,
            'station' => $station,
        ]);

        if ($result === ScanResult::Refused) {
            app(SendAlert::class)->toTenantMembers(
                NotificationType::TicketRefused,
                ['event' => $event->name],
                route('tenants.events.scan.index', [Tenant::current(), $event], absolute: false),
                except: $actor,
            );
        }
    }

    /**
     * @return array{
     *     result: ScanResult,
     *     forced: bool,
     *     registration: array{name: string, unit: string, partySize: int, guestOf: string|null, tableNumber: int|null}|null,
     *     firstScannedAt: CarbonInterface|null,
     *     firstScannedBy: string|null,
     * }
     */
    private function outcome(
        ScanResult $result,
        bool $forced,
        ?Ticket $ticket,
        ?CarbonInterface $firstScannedAt,
        ?string $firstScannedBy,
    ): array {
        return [
            'result' => $result,
            'forced' => $forced,
            // Un billet fait entrer une seule personne (README 2.8, un billet par personne) : le nom
            // et l'unite sont ceux de son titulaire, `guestOf` nomme l'invite d'un accompagnateur.
            'registration' => $ticket === null ? null : [
                'name' => $ticket->holderName(),
                'unit' => $ticket->holderUnitName(),
                'partySize' => 1,
                'guestOf' => $ticket->isCompanion() ? $ticket->registration->name : null,
                'tableNumber' => $ticket->registration->tableAssignment?->seatingTable->number,
            ],
            'firstScannedAt' => $firstScannedAt,
            'firstScannedBy' => $firstScannedBy,
        ];
    }
}
