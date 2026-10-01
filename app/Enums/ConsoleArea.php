<?php

namespace App\Enums;

/**
 * Les ecrans de la console d'exploitation (README section 3, ecrans 27 a 34), tels qu'un profil
 * editeur les ouvre ou non. Catalogue ferme : une zone correspond a un controle reel sur une route.
 */
enum ConsoleArea: string
{
    case Organisations = 'organisations';
    case OrganisationActions = 'organisation_actions';
    case Recovery = 'recovery';
    case Plans = 'plans';
    case Health = 'health';
    case Showcase = 'showcase';
    case Audit = 'audit';
    case Team = 'team';
    case Support = 'support';
}
