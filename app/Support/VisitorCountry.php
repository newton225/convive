<?php

namespace App\Support;

use Illuminate\Http\Request;
use libphonenumber\PhoneNumberUtil;

/**
 * Le pays preselectionne dans le champ telephone d'un invite (decision du proprietaire du projet,
 * 2026-09-29). Cloudflare deduit le pays de l'adresse IP et le pose dans `CF-IPCountry` : aucune
 * base a tenir, et l'adresse n'est transmise a personne. Sans Cloudflare devant l'application (en
 * local notamment), la Cote d'Ivoire.
 *
 * L'en-tete peut etre forge par un client qui atteint le serveur sans passer par Cloudflare : sans
 * consequence, il ne choisit qu'un drapeau par defaut, que l'invite peut changer. Le numero reste
 * valide par le serveur (`PhoneNumber::normalize`), quel que soit le pays affiche.
 */
class VisitorCountry
{
    public const Default = 'CI';

    public static function from(Request $request): string
    {
        $country = strtoupper((string) $request->header('CF-IPCountry'));

        // Seuls les pays que libphonenumber sait numeroter : XX (inconnu) et T1 (Tor) en sont exclus.
        return in_array($country, PhoneNumberUtil::getInstance()->getSupportedRegions(), true)
            ? $country
            : self::Default;
    }
}
