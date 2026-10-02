<?php

namespace App\Enums;

/**
 * Les trois profils de l'equipe editeur (README section 3) : Fondateur (tout), Support (lecture
 * des organisations, acces de support), Comptabilite (facturation, plans, suspensions). Figes dans
 * le code, contrairement aux profils d'une organisation : ils gouvernent l'acces a toutes les
 * organisations a la fois, et ne se composent pas depuis un ecran.
 */
enum ConsoleProfile: string
{
    case Founder = 'founder';
    case Support = 'support';
    case Accounting = 'accounting';

    /**
     * Get the areas of the console this profile opens.
     *
     * @return array<int, ConsoleArea>
     */
    public function areas(): array
    {
        return match ($this) {
            self::Founder => ConsoleArea::cases(),
            // Le Support repond a « le message est-il parti ? » : il lit le releve des envois.
            self::Support => [ConsoleArea::Organisations, ConsoleArea::Support, ConsoleArea::Messages],
            self::Accounting => [
                ConsoleArea::Organisations, ConsoleArea::OrganisationActions,
                ConsoleArea::Recovery, ConsoleArea::Plans,
            ],
        };
    }

    public function allows(ConsoleArea $area): bool
    {
        return in_array($area, $this->areas(), true);
    }

    /**
     * Get the label shown in the console.
     */
    public function label(): string
    {
        return __("console.team.profiles.{$this->value}");
    }
}
