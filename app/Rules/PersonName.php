<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un nom de personne : lettres de toute langue (accents compris), espaces, tirets et apostrophes (Kouame,
 * N'Guessan, Jean-Marc). Ni chiffre ni symbole. Doit commencer par une lettre.
 */
class PersonName implements ValidationRule
{
    public const Pattern = "/^\p{L}[\p{L}\p{M}'’ \-]*$/u";

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::Pattern, $value) !== 1) {
            $fail(__('guest.registration.errors.name_invalid'));
        }
    }
}
