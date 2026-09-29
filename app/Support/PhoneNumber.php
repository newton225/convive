<?php

namespace App\Support;

use Propaganistas\LaravelPhone\PhoneNumber as LibPhoneNumber;

/**
 * Numeros de telephone, ramenes a une forme unique (E.164 : `+`, indicatif, numero). Un meme
 * telephone s'ecrit de mille facons (`07 07...`, `+225 07...`, `00225...`, `(+225)...`) ; sans forme
 * unique, il passait pour plusieurs numeros, et l'attente imposee apres des reservations expirees
 * se contournait en ajoutant ou retirant l'indicatif (SECURITY.md C3).
 *
 * Deux perimetres (decisions du proprietaire du projet, 2026-09-29) :
 * - le telephone d'un invite, de tout pays (`normalize`) : il ne sert qu'a le joindre, le paiement
 *   passe par une preuve deposee, pas par ce numero. Sans indicatif, il est lu comme ivoirien ;
 * - le numero d'un compte de versement, ivoirien seulement (`normalizeIvorian`) : son prefixe
 *   designe le reseau Mobile Money, et un numero d'un autre reseau detournerait l'argent.
 */
class PhoneNumber
{
    public const CountryCode = '225';

    /**
     * Prefixes valides d'un numero a 10 chiffres : mobiles (Moov 01, MTN 05, Orange 07) et fixes
     * (21, 25, 27).
     */
    public const Prefixes = ['01', '05', '07', '21', '25', '27'];

    /**
     * Normalize a guest's number, from any country, to E.164, or `null` if it is not a valid one.
     *
     * L'ecriture ivoirienne passe d'abord par sa propre lecture, qui accepte aussi l'indicatif sans
     * `+` (`225 07...`), inconnu de libphonenumber. Un numero sans indicatif est lu comme ivoirien.
     */
    public static function normalize(?string $input): ?string
    {
        if ($ivorian = self::normalizeIvorian($input)) {
            return $ivorian;
        }

        // libphonenumber ne lit pas le prefixe international `00` : on le remplace par `+`.
        $candidate = preg_replace('/^\s*00/', '+', (string) $input) ?? '';

        if (trim($candidate) === '') {
            return null;
        }

        $number = new LibPhoneNumber($candidate, 'CI');

        return $number->isValid() ? $number->formatE164() : null;
    }

    /**
     * Normalize any writing of an Ivorian number to `+225XXXXXXXXXX`, or `null` if it is not one.
     */
    public static function normalizeIvorian(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        $national = match (true) {
            strlen($digits) === 15 && str_starts_with($digits, '00'.self::CountryCode) => substr($digits, 5),
            strlen($digits) === 13 && str_starts_with($digits, self::CountryCode) => substr($digits, 3),
            // Dix chiffres sans indicatif seulement : `+` ou `00` en tete annonce un autre pays.
            strlen($digits) === 10 && preg_match('/^\s*(\+|00)/', (string) $input) !== 1 => $digits,
            default => null,
        };

        if ($national === null || ! in_array(substr($national, 0, 2), self::Prefixes, true)) {
            return null;
        }

        return '+'.self::CountryCode.$national;
    }

    /**
     * Format a normalized number for display, in pairs : `+225 07 07 12 34 56`.
     */
    public static function format(string $normalized): string
    {
        return '+'.self::CountryCode.' '.implode(' ', str_split(substr($normalized, 4), 2));
    }

    /**
     * Get the two-digit prefix that designates the operator (`07` for Orange, for instance).
     */
    public static function prefix(string $normalized): string
    {
        return substr($normalized, 4, 2);
    }

    /**
     * Determine whether two writings designate the same telephone.
     *
     * Les lignes enregistrees avant la normalisation gardent leur ecriture d'origine : on
     * normalise les deux cotes, et on retombe sur les chiffres seuls pour un numero qui n'en a
     * pas de forme valide.
     */
    public static function same(string $first, string $second): bool
    {
        return self::key($first) === self::key($second);
    }

    private static function key(string $number): string
    {
        return self::normalize($number) ?? (preg_replace('/\D+/', '', $number) ?? '');
    }
}
