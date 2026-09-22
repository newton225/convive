<?php

namespace App\Support;

use Illuminate\Support\Str;

class Subdomain
{
    /**
     * Sous-domaines que l'application se reserve. Les laisser prendre par un locataire
     * rendrait joignable, sous son controle, une adresse que les utilisateurs associent au
     * produit lui-meme.
     *
     * @var array<int, string>
     */
    public const Reserved = [
        'account', 'accounts', 'admin', 'api', 'app', 'assets', 'auth', 'billing', 'blog',
        'cdn', 'convive', 'dashboard', 'dev', 'docs', 'ftp', 'help', 'login', 'mail',
        'preprod', 'secure', 'staging', 'static', 'status', 'support', 'test', 'www',
    ];

    /**
     * Minimum length. Two characters and under are kept for future product use.
     */
    public const MinimumLength = 3;

    public const MaximumLength = 63;

    /**
     * Determine whether the given subdomain is reserved by the application.
     */
    public static function isReserved(string $subdomain): bool
    {
        return in_array(Str::lower(trim($subdomain)), self::Reserved, true);
    }

    /**
     * Normalise a subdomain as it is stored: lowercase, trimmed.
     */
    public static function normalise(string $subdomain): string
    {
        return Str::lower(trim($subdomain));
    }
}
