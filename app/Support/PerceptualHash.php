<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Empreinte perceptuelle par moyenne (average hash) sur 8x8 pixels en niveaux de gris,
 * calculee avec l'extension GD, sans dependance supplementaire (README 2.9).
 *
 * Une empreinte cryptographique (SHA-256, par exemple) changerait entierement pour un recadrage
 * ou une recompression d'un pixel : elle ne detecterait qu'un fichier identique octet pour
 * octet. Celle-ci tolere ces variations mineures, ce qui correspond a « la meme capture envoyee
 * deux fois », le signal vise par le README.
 */
class PerceptualHash
{
    /**
     * Cote de la grille reduite avant comparaison : 64 pixels, donc 64 bits d'empreinte.
     */
    private const GridSize = 8;

    /**
     * Compute the perceptual hash of image bytes, as a 16-character hexadecimal string.
     */
    public static function forImageContents(string $contents): string
    {
        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Image illisible pour le calcul de l\'empreinte perceptuelle.');
        }

        try {
            $grid = imagecreatetruecolor(self::GridSize, self::GridSize);
            imagecopyresampled(
                $grid, $source,
                0, 0, 0, 0,
                self::GridSize, self::GridSize,
                imagesx($source), imagesy($source),
            );

            $luminances = [];
            for ($y = 0; $y < self::GridSize; $y++) {
                for ($x = 0; $x < self::GridSize; $x++) {
                    $rgb = imagecolorat($grid, $x, $y);

                    if ($rgb === false) {
                        throw new RuntimeException('Pixel illisible pour le calcul de l\'empreinte perceptuelle.');
                    }

                    $colors = imagecolorsforindex($grid, $rgb);
                    $luminances[] = (int) round(
                        0.299 * $colors['red'] + 0.587 * $colors['green'] + 0.114 * $colors['blue'],
                    );
                }
            }

            $average = array_sum($luminances) / count($luminances);

            $bits = '';
            foreach ($luminances as $luminance) {
                $bits .= $luminance >= $average ? '1' : '0';
            }

            // Quatre bits a la fois : `bindec()` perdrait en precision sur les 64 bits d'un
            // coup dans un flottant des que le bit de poids fort vaut 1, ni GMP ni BCMath
            // n'etant garantis presents sur l'hebergement (voir CLAUDE.md, « Paquets »).
            $hex = '';
            foreach (str_split($bits, 4) as $nibble) {
                $hex .= base_convert($nibble, 2, 16);
            }

            return $hex;
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * Get the number of differing bits between two hashes : the standard distance for
     * comparing perceptual hashes. Zero means identical, `DuplicateHashThreshold` and below on
     * `PaymentProof` means « the same capture ».
     */
    public static function hammingDistance(string $a, string $b): int
    {
        $distance = 0;

        for ($i = 0; $i < strlen($a); $i++) {
            $distance += substr_count(decbin(hexdec($a[$i]) ^ hexdec($b[$i])), '1');
        }

        return $distance;
    }
}
