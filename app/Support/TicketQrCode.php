<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Rendu de l'image du QR d'un billet (README ecran 7), avec `bacon/bacon-qr-code` : le choix retenu
 * pour un backend PHP pur, `bwip-js` (cite par CLAUDE.md) etant une bibliotheque JavaScript sans
 * equivalent server-side sans faire tourner Node a cote de Laravel.
 *
 * Modules arrondis et relies entre eux, reperes d'angle compris : l'allure change, pas la surface
 * sombre de chaque module, dont depend la lecture. En SVG, le seul rendu de ce style sans Imagick ;
 * la page de l'invite et le moteur PDF l'affichent tous deux, net a toute taille.
 */
class TicketQrCode
{
    /**
     * Taille du cote de l'image, en pixels : assez grand pour rester lisible a la camera d'un
     * telephone tenu a bout de bras.
     */
    private const SizePixels = 320;

    /**
     * Marge blanche autour du code, en modules. Le cadre blanc du billet la prolonge.
     */
    private const MarginModules = 2;

    /**
     * Render the QR code shown in a ticket template preview : the look and density of a real
     * ticket, but unsigned, so a scan refuses it.
     */
    public static function sample(): string
    {
        return self::dataUri(str_repeat('convive-apercu-', 17));
    }

    /**
     * Render the given signed token as a QR code, returned as a data URI ready for an <img> tag.
     */
    public static function dataUri(string $token): string
    {
        $style = new RendererStyle(
            self::SizePixels,
            self::MarginModules,
            new RoundnessModule(RoundnessModule::MEDIUM),
        );

        $svg = (new Writer(new ImageRenderer($style, new SvgImageBackEnd)))->writeString($token);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
