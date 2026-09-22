<?php

namespace App\Enums;

/**
 * Formes juridiques de l'espace OHADA, plus les formes non commerciales courantes chez les
 * organisateurs d'evenements. Catalogue ferme : la forme juridique apparait sur les recus et
 * les billets, elle ne se saisit pas en texte libre.
 */
enum LegalForm: string
{
    case Association = 'association';
    case Ngo = 'ngo';
    case ReligiousBody = 'religious_body';
    case Foundation = 'foundation';
    case Cooperative = 'cooperative';
    case SoleProprietorship = 'sole_proprietorship';
    case Sarl = 'sarl';
    case Sarlu = 'sarlu';
    case Sa = 'sa';
    case Sas = 'sas';
    case Sasu = 'sasu';
    case Gie = 'gie';
    case PublicBody = 'public_body';
    case Other = 'other';

    /**
     * Get the label shown to the operator.
     */
    public function label(): string
    {
        return __("organisation.legal_forms.{$this->value}");
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $form) => $form->value, self::cases());
    }
}
