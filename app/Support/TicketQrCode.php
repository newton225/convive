<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Rendu de l'image du QR d'un billet (README ecran 7), avec `endroid/qr-code` : le choix retenu
 * pour un backend PHP pur, `bwip-js` (cite par CLAUDE.md) etant une bibliotheque JavaScript sans
 * equivalent server-side sans faire tourner Node a cote de Laravel.
 */
class TicketQrCode
{
    /**
     * Taille du cote de l'image, en pixels : assez grand pour rester lisible a la camera d'un
     * telephone tenu a bout de bras, sans faire un fichier inutilement lourd sur la page.
     */
    private const SizePixels = 320;

    /**
     * Render the given signed token as a QR code, returned as a data URI ready for an <img> tag.
     */
    public static function dataUri(string $token): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $token,
            size: self::SizePixels,
            margin: 10,
        ))->build()->getDataUri();
    }
}
