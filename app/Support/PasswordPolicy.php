<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Les regles d'un nouveau mot de passe (decision du proprietaire du projet, 2026-10-04) : les memes
 * partout, developpement compris. Huit caracteres, majuscule et minuscule, chiffre, symbole ; en
 * production seulement, le refus d'un mot de passe deja apparu dans une fuite (le service interroge
 * est sur Internet).
 *
 * Seule source des regles : la validation (`Password::defaults()`) et l'indicateur affiche a l'ecran
 * lisent la meme chose.
 */
final class PasswordPolicy
{
    public const MinLength = 8;

    public static function rule(): Password
    {
        $rule = Password::min(self::MinLength)->letters()->mixedCase()->numbers()->symbols();

        return app()->isProduction() ? $rule->uncompromised() : $rule;
    }

    /**
     * What the strength indicator checks while the person types.
     *
     * @return array{min: int, mixedCase: bool, numbers: bool, symbols: bool, uncompromised: bool}
     */
    public static function forDisplay(): array
    {
        return [
            'min' => self::MinLength,
            'mixedCase' => true,
            'numbers' => true,
            'symbols' => true,
            'uncompromised' => app()->isProduction(),
        ];
    }
}
