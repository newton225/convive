<?php

namespace App\Support;

/**
 * Montants en francs CFA, sans decimale (CLAUDE.md, README section 7) : un entier est la
 * representation exacte, jamais une approximation. Miroir de `resources/js/lib/format-currency.ts`,
 * pour les gabarits PDF rendus cote serveur (exports, rapports) : les colonnes Excel/CSV restent
 * des entiers bruts, pour rester sommables au tableur.
 *
 * `number_format()` plutot que `NumberFormatter` : ce dernier exige l'extension `intl`, absente de
 * nombreux PHP (dont celui du developpement, erreur 500 sur l'export PDF le 2026-09-27). Montant
 * entier et deux langues servies seulement : les separateurs se declarent ici, a l'identique de ce
 * que `Intl.NumberFormat` produit dans le navigateur, a une nuance pres : l'espace insecable ordinaire
 * (U+00A0) et non l'espace fine insecable (U+202F), que la police du moteur PDF ne connait pas et
 * rend « ? » (« 10?000 F CFA » sur le billet, 2026-10-16).
 */
class Money
{
    private const ThousandsSeparators = [
        'fr' => "\u{00A0}",
        'en' => ',',
    ];

    public static function format(int $amount): string
    {
        $separator = self::ThousandsSeparators[app()->getLocale()] ?? self::ThousandsSeparators['fr'];

        return number_format($amount, 0, '', $separator).' F CFA';
    }
}
