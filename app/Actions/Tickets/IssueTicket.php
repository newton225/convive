<?php

namespace App\Actions\Tickets;

use App\Models\Registration;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;

/**
 * Emission des billets d'une inscription confirmee (README 2.8, ecran 7), etape 7 de « Ordre de
 * construction ». Appelee juste apres la confirmation, comme `App\Actions\Seating\AssignTable`
 * (voir `App\Actions\PaymentProofs\ValidatePaymentProof`).
 *
 * Un billet par personne (decision du 2026-09-27) : celui de l'invite, puis un billet nominatif par
 * accompagnateur, chacun avec son QR. Le jeton QR lui-meme n'est pas stocke : seul le nonce l'est,
 * il se recalcule a chaque affichage avec `App\Support\TicketToken::sign()`.
 */
class IssueTicket
{
    /**
     * Issue the group's tickets for the given confirmed registration, returning the main guest's.
     *
     * Idempotent : `Cache::lock()` par inscription (SQLite ignore `lockForUpdate()`, voir
     * CLAUDE.md, « Base de donnees ») empeche deux confirmations concurrentes de creer deux fois
     * les memes billets ; seuls les billets manquants sont emis. L'unicite reelle sur
     * `(registration_id, holder_position)` reste le filet en dernier ressort.
     */
    public function handle(Registration $registration): Ticket
    {
        return Cache::lock("registration:{$registration->id}:ticket", 10)->block(5, function () use ($registration) {
            $keyPair = $registration->event->ensureSigningKeyPair();
            $existing = Ticket::where('registration_id', $registration->id)->pluck('holder_position')->all();

            $issue = function (int $position, ?string $name, ?int $unitId) use ($registration, $keyPair, $existing): void {
                if (in_array($position, $existing, true)) {
                    return;
                }

                Ticket::create([
                    'registration_id' => $registration->id,
                    'holder_position' => $position,
                    'holder_name' => $name,
                    'holder_unit_id' => $unitId,
                    'nonce' => Ticket::generateNonce(),
                    'key_version' => $keyPair['version'],
                    'issued_at' => now(),
                ]);
            };

            $issue(Ticket::GuestPosition, null, null);

            foreach ($registration->companions()->orderBy('position')->get() as $index => $companion) {
                $issue($index + 1, $companion->name, $companion->unit_id);
            }

            return Ticket::where('registration_id', $registration->id)
                ->where('holder_position', Ticket::GuestPosition)
                ->firstOrFail();
        });
    }
}
