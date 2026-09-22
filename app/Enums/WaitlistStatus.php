<?php

namespace App\Enums;

/**
 * Cycle de vie d'une entree de liste d'attente (README 2.3).
 *
 * `Waiting` -> `Invited` (une place s'est liberee, lien de six heures) -> `Converted` (finalisee
 * en inscription) ou `Expired` (delai ecoule sans reponse, on passe au suivant).
 */
enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Invited = 'invited';
    case Expired = 'expired';
    case Converted = 'converted';

    public function label(): string
    {
        return __("waitlist.statuses.{$this->value}");
    }
}
