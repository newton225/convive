<?php

namespace Tests\Unit\Support;

use App\Support\VisitorCountry;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Le pays preselectionne dans le champ telephone d'un invite : celui que Cloudflare deduit de
 * l'adresse IP (`CF-IPCountry`), la Cote d'Ivoire sinon.
 */
class VisitorCountryTest extends TestCase
{
    private function requestWith(?string $country): Request
    {
        $request = Request::create('/');

        if ($country !== null) {
            $request->headers->set('CF-IPCountry', $country);
        }

        return $request;
    }

    public function test_le_pays_annonce_par_cloudflare_est_retenu(): void
    {
        $this->assertSame('FR', VisitorCountry::from($this->requestWith('FR')));
        $this->assertSame('SN', VisitorCountry::from($this->requestWith('sn')));
    }

    public function test_sans_en_tete_la_cote_d_ivoire_est_retenue(): void
    {
        $this->assertSame('CI', VisitorCountry::from($this->requestWith(null)));
    }

    public function test_une_valeur_inconnue_ou_speciale_retombe_sur_la_cote_d_ivoire(): void
    {
        // Cloudflare envoie XX (pays inconnu) et T1 (reseau Tor).
        $this->assertSame('CI', VisitorCountry::from($this->requestWith('XX')));
        $this->assertSame('CI', VisitorCountry::from($this->requestWith('T1')));
        $this->assertSame('CI', VisitorCountry::from($this->requestWith('<script>')));
    }
}
