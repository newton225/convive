<?php

namespace App\Support;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;

/**
 * Liste de revocation signee des billets d'un evenement (SECURITY.md C2).
 *
 * Le scan hors ligne verifie l'authenticite d'un billet, pas son etat : une inscription annulee
 * apres la derniere synchronisation reste valide pour la signature. Cette liste, signee avec la
 * meme cle Ed25519 que les billets et dans le meme format que `TicketToken`, est recuperee par
 * l'appareil de l'agent a chaque retour du reseau. Signee, elle ne peut pas etre videe par
 * quelqu'un qui aurait acces au stockage local du telephone.
 */
final class TicketRevocationList
{
    public static function signedFor(Event $event): string
    {
        $keyPair = $event->ensureSigningKeyPair();

        $revoked = Registration::query()
            ->where('event_id', $event->id)
            ->where('status', '!=', RegistrationStatus::Confirmed)
            ->whereHas('ticket')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return TicketToken::sign([
            'tenant_id' => Tenant::current()?->id,
            'event_id' => $event->id,
            'key_version' => $keyPair['version'],
            'issued_at' => now()->getTimestamp(),
            'revoked' => $revoked,
        ], $keyPair['secret']);
    }
}
