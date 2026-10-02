<?php

namespace App\Support\Search;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * Une colonne ramenee a sa forme comparable, sans accents et en minuscules, pour
 * `UnaccentedSearch`. Le nom de la colonne est protege par la grammaire de la connexion : c'est le
 * point d'extension prevu par Laravel pour une expression qui depend du moteur.
 */
final readonly class UnaccentedColumn implements Expression
{
    public function __construct(private string $column)
    {
        //
    }

    public function getValue(Grammar $grammar): string
    {
        return 'lower(unaccent('.$grammar->wrap($this->column).'))';
    }
}
