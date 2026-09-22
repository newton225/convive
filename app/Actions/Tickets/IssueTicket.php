<?php

namespace App\Actions\Tickets;

use App\Models\Registration;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;

/**
 * Emission du billet d'une inscription confirmee (README 2.8, ecran 7), etape 7 de
 * « Ordre de construction ». Appelee juste apres la confirmation, comme
 * `App\Actions\Seating\AssignTable` (voir `App\Actions\PaymentProofs\ValidatePaymentProof`).
 *
 * Le jeton QR lui-meme (charge signee) n'est pas stocke : seul le nonce l'est, il se recalcule
 * a la demande avec `App\Support\TicketToken::sign()` a chaque affichage du billet, a partir de
 * la cle courante de l'evenement.
 */
class IssueTicket
{
    /**
     * Issue the ticket for the given confirmed registration, or return the existing one.
     *
     * Idempotent : `Cache::lock()` par inscription (SQLite ignore `lockForUpdate()`, voir
     * CLAUDE.md, « Base de donnees ») empeche deux confirmations concurrentes de la meme
     * inscription (rejeu, double clic) de creer deux billets ; la contrainte d'unicite reelle
     * sur `registration_id` reste le filet en dernier ressort.
     */
    public function handle(Registration $registration): Ticket
    {
        return Cache::lock("registration:{$registration->id}:ticket", 10)->block(5, function () use ($registration) {
            $existing = Ticket::where('registration_id', $registration->id)->first();

            if ($existing !== null) {
                return $existing;
            }

            $keyPair = $registration->event->ensureSigningKeyPair();

            return Ticket::create([
                'registration_id' => $registration->id,
                'nonce' => Ticket::generateNonce(),
                'key_version' => $keyPair['version'],
                'issued_at' => now(),
            ]);
        });
    }
}
