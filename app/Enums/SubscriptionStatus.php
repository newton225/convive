<?php

namespace App\Enums;

/**
 * L'etat d'un abonnement (README section 3, « Facturation »). `PastDue` demarre le compte a
 * rebours de la relance (J+3) et de la suspension (J+10) ; `Suspended` arrete les operations de
 * l'espace jusqu'au reglement.
 */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Canceled = 'canceled';

    public function label(): string
    {
        return __("billing.statuses.{$this->value}");
    }
}
