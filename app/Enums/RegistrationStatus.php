<?php

namespace App\Enums;

/**
 * Cycle de vie d'une inscription (README 2.1). `Deleted` n'y figure pas : une inscription
 * purgee n'est pas marquee, elle est supprimee (voir CLAUDE.md, « Purge automatique »).
 *
 * Seul `Draft` est atteignable par le code a l'etape 4 : les transitions vers `Held` et
 * au-dela appartiennent au noyau de reservation, etape 5.
 *
 * `Cancelled` (etape 9) est distinct d'`Expired`/`ProofRejected` : ces deux derniers sont des
 * echecs du parcours invite, `Cancelled` est une decision de l'organisation, a n'importe quel
 * stade y compris `Confirmed`. Voir `App\Actions\Registrations\CancelRegistration`.
 */
enum RegistrationStatus: string
{
    case Draft = 'draft';
    case Held = 'held';
    case ProofSubmitted = 'proof_submitted';
    case Confirmed = 'confirmed';
    case Expired = 'expired';
    case ProofRejected = 'proof_rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("registrations.statuses.{$this->value}");
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
