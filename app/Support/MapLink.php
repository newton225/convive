<?php

namespace App\Support;

/**
 * Le lien de localisation d'un evenement (decision du proprietaire du projet, 2026-10-07) : un lien
 * vers un service de cartes connu, ou des coordonnees que l'on convertit en lien. Il est presente
 * aux invites : un lien vers n'importe quel site deviendrait un hameconnage signe par l'organisation.
 */
final class MapLink
{
    /**
     * Les services acceptes, par hote, et le debut de chemin exige quand l'hote sert aussi autre
     * chose que des cartes (google.com est aussi un moteur de recherche).
     *
     * @var array<string, string>
     */
    private const Hosts = [
        'maps.app.goo.gl' => '/',
        'goo.gl' => '/maps',
        'maps.google.com' => '/',
        'www.google.com' => '/maps',
        'google.com' => '/maps',
        'maps.apple.com' => '/',
        'www.openstreetmap.org' => '/',
        'openstreetmap.org' => '/',
        'osm.org' => '/',
        'www.waze.com' => '/',
        'waze.com' => '/',
    ];

    private const Coordinates = '/^\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/';

    /**
     * Turn coordinates (« 5.3364, -4.0267 ») into a map link ; anything else is returned trimmed.
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match(self::Coordinates, $value, $matches) === 1) {
            [, $latitude, $longitude] = $matches;

            if (abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180) {
                return "https://www.google.com/maps/search/?api=1&query={$latitude},{$longitude}";
            }
        }

        return $value;
    }

    /**
     * Determine whether the link points to a known map service, over HTTPS.
     */
    public static function isAllowed(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            return false;
        }

        $prefix = self::Hosts[strtolower($parts['host'])] ?? null;

        return $prefix !== null && str_starts_with($parts['path'] ?? '/', $prefix);
    }
}
