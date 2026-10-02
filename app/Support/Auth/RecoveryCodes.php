<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Laravel\Fortify\Fortify;

/**
 * Codes de secours de la double authentification (SECURITY.md M5) : haches, a usage unique,
 * denombres.
 *
 * Fortify les garde chiffres mais lisibles : l'application sait les reafficher, et qui prend la
 * base avec la cle les lit. Ici seule leur empreinte SHA-256 est gardee (un code de secours est
 * un aleatoire long, pas un mot de passe : une empreinte rapide suffit, comme pour les jetons de
 * reprise). L'empreinte reste rangee dans la colonne chiffree de Fortify, sous la meme forme de
 * liste : ses lectures continuent de fonctionner, elles ne rendent simplement plus rien d'utile.
 */
class RecoveryCodes
{
    /**
     * Cle de session des codes tout juste crees, en attente de leur unique affichage.
     */
    public const SessionKey = 'two_factor.fresh_recovery_codes';

    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * Replace the user's recovery codes with the fingerprints of the given ones.
     *
     * @param  array<int, string>  $codes
     */
    public static function replace(User $user, array $codes): void
    {
        self::store($user, array_map(self::hash(...), array_values($codes)));
    }

    /**
     * Turn the readable codes Fortify just stored into fingerprints, and return the readable ones
     * for their single display.
     *
     * @return array<int, string>
     */
    public static function seal(User $user): array
    {
        $codes = array_values(array_filter(self::entries($user), fn (string $entry) => ! self::isFingerprint($entry)));

        self::store($user, array_map(self::fingerprint(...), self::entries($user)));

        return $codes;
    }

    /**
     * Get the stored fingerprint the given code matches, or null.
     */
    public static function match(User $user, string $code): ?string
    {
        $candidate = self::hash($code);

        foreach (self::fingerprints($user) as $fingerprint) {
            if (hash_equals($fingerprint, $candidate)) {
                return $fingerprint;
            }
        }

        return null;
    }

    /**
     * Remove a used code : un code de secours ne sert qu'une fois, et n'est pas remplace par un
     * nouveau que personne ne verrait.
     */
    public static function consume(User $user, string $fingerprint): void
    {
        self::store($user, array_values(array_filter(
            self::fingerprints($user),
            fn (string $stored) => ! hash_equals($stored, $fingerprint),
        )));
    }

    public static function remaining(User $user): int
    {
        return count(self::entries($user));
    }

    /**
     * @return array<int, string>
     */
    private static function fingerprints(User $user): array
    {
        return array_map(self::fingerprint(...), self::entries($user));
    }

    /**
     * Un code enregistre en clair avant cette regle est lu comme son empreinte.
     */
    private static function fingerprint(string $entry): string
    {
        return self::isFingerprint($entry) ? $entry : self::hash($entry);
    }

    private static function isFingerprint(string $entry): bool
    {
        return strlen($entry) === 64 && ctype_xdigit($entry);
    }

    /**
     * @return array<int, string>
     */
    private static function entries(User $user): array
    {
        if ($user->two_factor_recovery_codes === null) {
            return [];
        }

        try {
            $entries = json_decode((string) Fortify::currentEncrypter()->decrypt($user->two_factor_recovery_codes), true);
        } catch (DecryptException) {
            // Illisible (cle changee) : aucun code ne vaut, l'utilisateur en genere de nouveaux.
            return [];
        }

        return is_array($entries) ? array_values(array_filter($entries, is_string(...))) : [];
    }

    /**
     * @param  array<int, string>  $fingerprints
     */
    private static function store(User $user, array $fingerprints): void
    {
        $user->forceFill([
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt((string) json_encode($fingerprints)),
        ])->save();
    }
}
