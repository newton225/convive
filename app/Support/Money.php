<?php

namespace App\Support;

use NumberFormatter;

/**
 * Montants en francs CFA, sans decimale (CLAUDE.md, README section 7) : un entier est la
 * representation exacte, jamais une approximation. Miroir de `resources/js/lib/format-currency.ts`,
 * pour les gabarits PDF rendus cote serveur (exports, rapports) : les colonnes Excel/CSV restent
 * des entiers bruts, pour rester sommables au tableur.
 */
class Money
{
    public static function format(int $amount): string
    {
        $formatter = new NumberFormatter(app()->getLocale(), NumberFormatter::DECIMAL);

        return $formatter->format($amount).' F CFA';
    }
}
