<?php

namespace App\Support;

/**
 * La couleur de texte a poser sur une couleur de marque. L'organisation choisit librement ses
 * couleurs : un texte toujours blanc devient illisible sur un vert vif ou un or, un texte toujours
 * fonce sur un bordeaux. On retient celui des deux qui contraste le plus, selon la formule de
 * luminance relative des WCAG 2.
 */
class ReadableTextColor
{
    public const Light = '#ffffff';

    public const Dark = '#1b1917';

    /**
     * @param  string  $background  Couleur hexadecimale, deja validee (`#rgb` ou `#rrggbb`).
     */
    public static function on(string $background): string
    {
        $luminance = self::luminance($background);

        $againstLight = (self::luminance(self::Light) + 0.05) / ($luminance + 0.05);
        $againstDark = ($luminance + 0.05) / (self::luminance(self::Dark) + 0.05);

        return $againstLight >= $againstDark ? self::Light : self::Dark;
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        [$red, $green, $blue] = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2),
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
