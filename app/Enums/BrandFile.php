<?php

namespace App\Enums;

/**
 * Les fichiers de marque d'une organisation et les fonds de son billet. Catalogue ferme : le nom de collection
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

    // L'image du haut du billet, derriere le QR (README ecran 15) : un reglage du billet, deposee
    // depuis son gabarit, pas une piece de l'identite de l'organisation.
    case TicketBackground = 'ticket_background';

    /**
     * La version recadree aux proportions du haut du talon : la page et le PDF affichent ainsi le
     * meme cadrage, le moteur PDF ne sachant pas recadrer une image a l'affichage.
     */
    public const TicketBackgroundConversion = 'ticket_stub';

    /**
     * Les proportions du haut du talon, en pixels de la version recadree. Le rognage propose a
     * l'exploitant les impose, et la Form Request les verifie.
     */
    public const TicketBackgroundWidth = 1000;

    public const TicketBackgroundHeight = 850;

    // L'image du bas du billet, sous la ligne perforee, derriere le texte. Le billet l'affiche
    // estompee : la lecture ne depend jamais de l'image choisie.
    case TicketBodyBackground = 'ticket_body_background';

    public const TicketBodyBackgroundConversion = 'ticket_body';

    /**
     * Les proportions du bas du talon dans le PDF (90 mm sur 110,5 mm), ou sa hauteur est fixe.
     */
    public const TicketBodyBackgroundWidth = 1000;

    public const TicketBodyBackgroundHeight = 1228;

    /**
     * Get the proportions imposed on this file, or null when it is stored as deposited.
     *
     * @return array{width: int, height: int}|null
     */
    public function crop(): ?array
    {
        return match ($this) {
            self::TicketBackground => ['width' => self::TicketBackgroundWidth, 'height' => self::TicketBackgroundHeight],
            self::TicketBodyBackground => ['width' => self::TicketBodyBackgroundWidth, 'height' => self::TicketBodyBackgroundHeight],
            default => null,
        };
    }

    /**
     * Get the name of the version cropped to those proportions, or null when there is none.
     */
    public function conversion(): ?string
    {
        return match ($this) {
            self::TicketBackground => self::TicketBackgroundConversion,
            self::TicketBodyBackground => self::TicketBodyBackgroundConversion,
            default => null,
        };
    }

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
     * Get the files edited on the organisation form (README ecran 14).
     *
     * @return array<int, self>
     */
    public static function organisationCases(): array
    {
        return [self::Logo, self::Banner, self::Stamp, self::Signature];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $file) => $file->value, self::cases());
    }
}
