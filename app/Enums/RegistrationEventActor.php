<?php

namespace App\Enums;

/**
 * Qui est a l'origine d'un fait de l'historique d'un dossier : l'invite lui-meme, ou le systeme
 * (tache planifiee, suite automatique d'un autre fait). Un geste de l'equipe se lit dans le journal
 * d'audit, avec son auteur.
 */
enum RegistrationEventActor: string
{
    case Guest = 'guest';
    case System = 'system';

    public function label(): string
    {
        return __('registrations.history.actors.'.$this->value);
    }
}
