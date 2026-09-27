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
}
