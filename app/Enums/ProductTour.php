<?php

namespace App\Enums;

/**
 * Les visites guidees du back-office. Catalogue ferme, comme les permissions : l'identifiant
 * arrive par l'URL quand un membre termine une visite, il ne s'enregistre jamais tel quel.
 */
enum ProductTour: string
{
    case Welcome = 'welcome';
    case FirstEvent = 'first_event';
    case Proofs = 'proofs';
    case EntryControl = 'entry_control';
    case Seating = 'seating';
    case Treasury = 'treasury';
    case Registrations = 'registrations';
    case Report = 'report';
    case Team = 'team';
    case Profiles = 'profiles';
    case Claims = 'claims';
    case Audit = 'audit';
    case Units = 'units';
    case PaymentAccounts = 'payment_accounts';
    case Reconciliation = 'reconciliation';
    case Organisation = 'organisation';
    case Billing = 'billing';
    case SupportAccess = 'support_access';
    case Console = 'console';
}
