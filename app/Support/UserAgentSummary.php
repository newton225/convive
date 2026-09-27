<?php

namespace App\Support;

/**
 * Resume lisible d'un agent utilisateur pour la liste des appareils connectes : navigateur,
 * systeme, mobile ou non. Volontairement grossier : il sert a reconnaitre son propre appareil,
 * pas a identifier une version exacte. L'ordre des tests compte, chaque navigateur recopiant les
 * jetons de ceux qu'il imite (Edge et Opera annoncent aussi Chrome, Chrome annonce aussi Safari).
 */
final class UserAgentSummary
{
    private const Browsers = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'Firefox/' => 'Firefox',
        'FxiOS/' => 'Firefox',
        'CriOS/' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const Platforms = [
        'iPhone' => 'iOS',
        'iPad' => 'iPadOS',
        'Android' => 'Android',
        'Windows' => 'Windows',
        'Macintosh' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    /**
     * @return array{browser: string|null, platform: string|null, mobile: bool}
     */
    public static function from(?string $userAgent): array
    {
        $userAgent ??= '';

        return [
            'browser' => self::firstMatch(self::Browsers, $userAgent),
            'platform' => self::firstMatch(self::Platforms, $userAgent),
            'mobile' => str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android'),
        ];
    }

    /**
     * @param  array<string, string>  $candidates
     */
    private static function firstMatch(array $candidates, string $userAgent): ?string
    {
        foreach ($candidates as $token => $label) {
            if (str_contains($userAgent, $token)) {
                return $label;
            }
        }

        return null;
    }
}
