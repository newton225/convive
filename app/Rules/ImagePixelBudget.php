<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Refuse une image dont le nombre de pixels depasserait la memoire du serveur au reencodage
 * (SECURITY.md H1, « limites de pixels imposees »).
 *
 * Le poids du fichier ne dit rien : une image se decode a environ quatre octets par pixel, quel que
 * soit son poids sur le disque. Un fichier de quelques octets qui declare 7000 x 7000 demande pres
 * de 200 Mo, et borner chaque cote ne suffit pas : c'est le produit des deux qui compte. Le plafond
 * laisse passer une photo de telephone ordinaire (12 megapixels) et tient dans 128 Mo de memoire.
 *
 * Seul l'en-tete est lu (`getimagesize`) : l'image n'est jamais decodee pour etre mesuree.
 */
class ImagePixelBudget implements ValidationRule
{
    public const MaxPixels = 16_000_000;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Ce qui n'est pas une image lisible est refuse par les regles `image` et `dimensions`.
        if (! $value instanceof UploadedFile) {
            return;
        }

        $size = @getimagesize($value->getPathname());

        if ($size === false) {
            return;
        }

        if ($size[0] * $size[1] > self::MaxPixels) {
            $fail(__('common.errors.image_too_large'));
        }
    }
}
