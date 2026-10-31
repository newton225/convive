<?php

namespace App\Enums;

/**
 * Comment une demande de changement d'un compte de versement se termine, autrement que par une alerte de
 * demande (`PaymentAccountChanged`) : le numero affiche aux invites change ou reste, et les porteurs de la
 * permission en sont prevenus (decision du 2026-10-31).
 */
enum PaymentAccountOutcome: string
{
    case Approved = 'approved';
    case Cancelled = 'cancelled';
    case Activated = 'activated';

    /**
     * La demande est-elle devenue le numero affiche aux invites ?
     */
    public function isApplied(): bool
    {
        return $this !== self::Cancelled;
    }
}
