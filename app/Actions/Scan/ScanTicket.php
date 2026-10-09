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
 *
 * @phpstan-type ScanOutcome array{
 *     result: ScanResult,
 *     forced: bool,
 *     manual: bool,
 *     registration: array{name: string, unit: string, partySize: int, guestOf: string|null, tableNumber: int|null}|null,
 *     firstScannedAt: CarbonInterface|null,
 *     firstScannedBy: string|null,
 *     otherEvent: array{name: string, venue: string|null, startsAt: string|null}|null,
 * }
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
     * @return ScanOutcome
     */
    public function handle(Event $event, string $token, User $actor, bool $force = false, ?string $station = null): array
    {
        $ticket = $this->resolveTicket($event, $token);

        if ($ticket === null) {
            // Deux evenements le meme jour (README ecran 26) : un vrai billet de l'autre reste
            // refuse ici, mais l'agent apprend ou l'invite est attendu, et ce n'est pas une
            // tentative de fraude a signaler.
            $otherEvent = $this->otherEventFor($event, $token);

            $this->journal($event, null, $actor, ScanResult::Refused, false, $station, alert: $otherEvent === null);

            return $this->outcome(ScanResult::Refused, false, null, null, null, $otherEvent);
        }

        return $this->admit($event, $ticket, $actor, $force, $station, manual: false);
    }

    /**
     * Admit the holder of a ticket found by the entrance search, without its QR being read
     * (README ecran 26 : ecran casse, telephone eteint, billet oublie).
     *
     * Memes regles qu'un billet scanne : inscription confirmee, evenement non clos, une seule
     * entree par billet, forcage trace. Le passage est journalise `manual` : la signature n'a pas
     * ete verifiee, c'est l'agent qui repond de l'identite de la personne.
     *
     * @return ScanOutcome
     */
    public function handleWithoutScan(Event $event, Ticket $ticket, User $actor, bool $force = false, ?string $station = null): array
    {
        if ($ticket->registration->event_id !== $event->id) {
            $this->journal($event, null, $actor, ScanResult::Refused, false, $station, alert: false, manual: true);

            return $this->outcome(ScanResult::Refused, false, null, null, null, manual: true);
        }

        return $this->admit($event, $ticket, $actor, $force, $station, manual: true);
    }

    /**
     * @return ScanOutcome
     */
    private function admit(Event $event, Ticket $ticket, User $actor, bool $force, ?string $station, bool $manual): array
    {
        if (! $this->ticketIsUsable($event, $ticket)) {
            // Sans scan, un refus n'est pas une tentative de fraude a signaler : l'inscription a
            // ete annulee ou l'evenement clos entre la recherche et la validation.
            $this->journal($event, null, $actor, ScanResult::Refused, false, $station, alert: ! $manual, manual: $manual);

            return $this->outcome(ScanResult::Refused, false, null, null, null, manual: $manual);
        }

        try {
            TicketArrival::create([
                'ticket_id' => $ticket->id,
                'performed_by_user_id' => $actor->id,
            ]);

            $this->journal($event, $ticket, $actor, ScanResult::Accepted, false, $station, manual: $manual);
            $this->reportEntryWithoutScan($event, $ticket, $actor, $manual);

            return $this->outcome(ScanResult::Accepted, false, $ticket, null, null, manual: $manual);
        } catch (QueryException) {
            $arrival = $ticket->arrival()->first();

            $this->journal($event, $ticket, $actor, ScanResult::AlreadyScanned, $force, $station, manual: $manual);
            $this->reportEntryWithoutScan($event, $ticket, $actor, $manual && $force);

            return $this->outcome(
                ScanResult::AlreadyScanned,
                $force,
                $force ? $ticket : null,
                $arrival?->created_at,
                $arrival !== null ? User::find($arrival->performed_by_user_id)?->name : null,
                manual: $manual,
            );
        }
    }

    /**
     * Tell those who watch the organisation's history that someone was let in without their
     * ticket being read : sans cette alerte, un agent qui fait entrer des complices sous le nom
     * d'invites pas encore arrives ne serait vu que de qui pense a relire les passages.
     */
    private function reportEntryWithoutScan(Event $event, Ticket $ticket, User $actor, bool $admittedWithoutScan): void
    {
        if (! $admittedWithoutScan) {
            return;
        }

        app(SendAlert::class)->toTenantMembers(
            NotificationType::EntryWithoutScan,
            ['event' => $event->name, 'agent' => $actor->name, 'guest' => $ticket->holderName()],
            route('tenants.events.scan.index', [Tenant::current(), $event], absolute: false),
            except: $actor,
        );
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
            || $this->expired($event, $payload['not_after'] ?? null)
            || $this->tooEarly($event)) {
            return null;
        }

        return Ticket::where('registration_id', $payload['registration_id'] ?? null)
            ->where('nonce', $payload['nonce'] ?? null)
            ->first();
    }

    /**
     * Find the other event of the organisation a genuine ticket belongs to, or null.
     *
     * L'identifiant d'evenement lu sans verification ne sert qu'a choisir la cle : le billet est
     * ensuite verifie entierement avec celle de cet evenement (signature, organisation, version de
     * cle, echeance, inscription confirmee). Un jeton forge ou perime ne revele donc rien, ni
     * l'existence de l'autre evenement ni son lieu.
     *
     * @return array{name: string, venue: string|null, startsAt: string|null}|null
     */
    private function otherEventFor(Event $event, string $token): ?array
    {
        $claimedId = TicketToken::claimedEventId($token);

        if ($claimedId === null || $claimedId === $event->id) {
            return null;
        }

        $other = Event::find($claimedId);

        if ($other === null) {
            return null;
        }

        $ticket = $this->resolveTicket($other, $token);

        if ($ticket === null || ! $this->ticketIsUsable($other, $ticket)) {
            return null;
        }

        return [
            'name' => $other->name,
            'venue' => $other->venue,
            'startsAt' => $other->starts_at?->toISOString(),
        ];
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
     * Determine whether the doors are not open yet : le billet est authentique mais l'organisateur
     * a fixe une heure d'ouverture (decision du 2026-10-09). Sans reglage, jamais trop tot.
     */
    private function tooEarly(Event $event): bool
    {
        $from = $event->ticketValidFrom();

        return $from !== null && now()->isBefore($from);
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

    private function journal(Event $event, ?Ticket $ticket, User $actor, ScanResult $result, bool $forced, ?string $station, bool $alert = true, bool $manual = false): void
    {
        ScanEvent::create([
            'event_id' => $event->id,
            'ticket_id' => $ticket?->id,
            'performed_by_user_id' => $actor->id,
            'result' => $result,
            'forced' => $forced,
            'manual' => $manual,
            'station' => $station,
        ]);

        if ($result === ScanResult::Refused && $alert) {
            app(SendAlert::class)->toTenantMembers(
                NotificationType::TicketRefused,
                ['event' => $event->name],
                route('tenants.events.scan.index', [Tenant::current(), $event], absolute: false),
                except: $actor,
            );
        }
    }

    /**
     * @param  array{name: string, venue: string|null, startsAt: string|null}|null  $otherEvent
     * @return ScanOutcome
     */
    private function outcome(
        ScanResult $result,
        bool $forced,
        ?Ticket $ticket,
        ?CarbonInterface $firstScannedAt,
        ?string $firstScannedBy,
        ?array $otherEvent = null,
        bool $manual = false,
    ): array {
        return [
            'result' => $result,
            'forced' => $forced,
            'manual' => $manual,
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
            'otherEvent' => $otherEvent,
        ];
    }
}
