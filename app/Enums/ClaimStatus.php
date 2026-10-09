<?php

namespace App\Enums;

/**
 * L'etat d'une reclamation : ouverte tant que personne ne l'a traitee, traitee ensuite. La reponse
 * a l'invite se donne hors de l'application (son telephone figure sur le dossier) : l'application
 * ne garde que le fait qu'elle a ete traitee.
 */
enum ClaimStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return __("claims.statuses.{$this->value}");
    }
}
