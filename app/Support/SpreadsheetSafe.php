<?php

namespace App\Support;

/**
 * Neutralisation des formules dans les exports tableur (SECURITY.md M2).
 *
 * Une valeur dont le premier caractere est `=`, `+`, `-`, `@`, une tabulation ou un retour
 * chariot est interpretee comme une formule par Excel a l'ouverture. Un invite choisit son
 * propre nom sur le formulaire public : sans cette garde, l'organisateur qui ouvre l'export
 * execute la formule sur son poste.
 *
 * Prefixage par une apostrophe (SECURITY.md : « prefixage par apostrophe ou refus des
 * caracteres de tete »). A n'appliquer qu'aux colonnes de texte libre : un numero de telephone
 * valide par `^\+?[0-9 ().-]{8,32}$` ne peut contenir aucun appel de fonction, et le prefixer
 * defigurerait un numero legitime commencant par `+`.
 */
class SpreadsheetSafe
{
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
