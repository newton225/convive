<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Encodage et signature du jeton QR d'un billet (README 2.8, SECURITY.md C2), etape 7 de
 * « Ordre de construction ».
 *
 * Signature asymetrique Ed25519 (extension `sodium`, presente par defaut depuis PHP 7.2, aucune
 * dependance supplementaire) : le scan doit fonctionner hors ligne, l'appareil de l'agent ne
 * porte que la cle publique de verification, jamais la cle privee qui signe. Un HMAC symetrique
 * exposerait ce secret sur chaque telephone.
 *
 * Format du jeton : `base64url(charge utile JSON) . '.' . base64url(signature)`. Ni un HMAC, ni
 * un JWS standard (en-tete d'algorithme, jeu de revendications normalise) : juste assez pour
 * verifier une charge fixe et connue des deux cotes, sans la complexite d'un format generique
 * dont rien ici n'a l'usage.
 */
class TicketToken
{
    /**
     * Sign the given payload with the event's secret key, returning the QR token string.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $payload, string $secretKeyBase64): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $secretKey = base64_decode($secretKeyBase64, true);

        if ($secretKey === false || $secretKey === '') {
            throw new InvalidArgumentException('Cle privee de signature invalide.');
        }

        $signature = sodium_crypto_sign_detached($json, $secretKey);

        return self::base64UrlEncode($json).'.'.self::base64UrlEncode($signature);
    }

    /**
     * Verify the given QR token against the event's public key, returning the decoded payload
     * when the signature checks out, or null for anything malformed or forged.
     *
     * @return array<string, mixed>|null
     */
    public static function verify(string $token, string $publicKeyBase64): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;

        $json = self::base64UrlDecode($encodedPayload);
        $signature = self::base64UrlDecode($encodedSignature);
        $publicKey = base64_decode($publicKeyBase64, true);

        if ($json === null || $signature === null || $publicKey === false || $publicKey === ''
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        if (! sodium_crypto_sign_verify_detached($signature, $json, $publicKey)) {
            return null;
        }

        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }

    private static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return null;
        }

        $padded = str_pad($value, (int) (4 * ceil(strlen($value) / 4)), '=');
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
