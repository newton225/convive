<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un nom complet naturel (decision du 2026-10-31) : des groupes de lettres de toute langue (accents compris),
 * separes chacun par un seul espace, tiret ou apostrophe (Kouame, N'Guessan, Jean-Marc). Ni chiffre, ni symbole,
 * ni separateur au debut, a la fin ou en double. Jamais trois fois la meme lettre de suite, au moins deux mots
 * (le prenom et le nom) et au moins deux lettres par mot.
 */
class PersonName implements ValidationRule
{
    public const Pattern = "/^(?:\p{L}\p{M}*)+(?:[ '’\-](?:\p{L}\p{M}*)+)*$/u";

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::Pattern, $value) !== 1) {
            $fail(__('guest.registration.errors.name_invalid'));

            return;
        }

        if (preg_match('/(\p{L})\1{2,}/iu', $value) === 1) {
            $fail(__('guest.registration.errors.name_repeated'));

            return;
        }

        // Les separateurs en double sont deja exclus : separer sur l'espace donne les mots.
        $words = explode(' ', $value);

        if (count($words) < 2 || array_filter($words, fn (string $word) => preg_match_all('/\p{L}/u', $word) < 2) !== []) {
            $fail(__('guest.registration.errors.name_words'));
        }
    }
}
