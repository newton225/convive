<?php

namespace App\Enums;

/**
 * Les faits de l'historique d'un dossier (`registration_events`). Catalogue ferme : chaque entree a
 * son libelle dans `lang/{fr,en}/registrations.php`, groupe `history.types`.
 */
enum RegistrationEventType: string
{
    case PhoneVerified = 'phone_verified';
    case Held = 'held';
    case ProofSubmitted = 'proof_submitted';
    case HoldExpired = 'hold_expired';
    case WaitlistJoined = 'waitlist_joined';
    case WaitlistInvited = 'waitlist_invited';
    case WaitlistConverted = 'waitlist_converted';
    case TableAssigned = 'table_assigned';
    case TicketsIssued = 'tickets_issued';
    case ClaimSubmitted = 'claim_submitted';

    public function label(): string
    {
        return __('registrations.history.types.'.$this->value);
    }
}
