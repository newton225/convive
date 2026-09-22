<?php

namespace App\Enums;

/**
 * Les quatre fichiers de marque d'une organisation. Catalogue ferme : le nom de collection
 * arrive par l'URL, il ne doit jamais etre une chaine libre.
 *
 * Le cachet et la signature sont apposes sur les billets, les recus et les exports PDF : ce
 * sont des pieces a valeur probante, pas des ornements.
 */
enum BrandFile: string
{
    case Logo = 'logo';
    case Banner = 'banner';
    case Stamp = 'stamp';
    case Signature = 'signature';

    /**
     * Get the label shown to the operator.
     */
    public function label(): string
    {
        return __("organisation.files.{$this->value}.label");
    }

    /**
     * Get the sentence explaining what the file is used for.
     */
    public function hint(): string
    {
        return __("organisation.files.{$this->value}.hint");
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $file) => $file->value, self::cases());
    }
}
