<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * SECURITY.md H7 : CSP a nonces sans unsafe-inline, plus HSTS, X-Content-Type-Options,
 * X-Frame-Options, Referrer-Policy et une Permissions-Policy restrictive, sur toute reponse.
 * `route('login')` sert de page guest stable pour l'assertion, la regle etant globale
 * (middleware du groupe `web`) et non specifique a cette route.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_la_reponse_porte_une_csp_stricte_avec_un_nonce_et_sans_unsafe_inline()
    {
        $csp = $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertMatchesRegularExpression("/script-src[^;]*'nonce-[A-Za-z0-9+\/=]+'/", $csp);
        $this->assertMatchesRegularExpression("/style-src[^;]*'nonce-[A-Za-z0-9+\/=]+'/", $csp);
    }

    public function test_la_csp_autorise_les_images_blob_pour_l_apercu_des_recus_et_rien_d_autre_en_blob()
    {
        $csp = $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/img-src 'self' data: blob:/", $csp);
        // Un blob ne doit jamais devenir du script, un cadre ou un objet.
        $this->assertDoesNotMatchRegularExpression('/(script-src|frame-src|object-src|default-src)[^;]*blob:/', $csp);
    }

    public function test_chaque_reponse_porte_un_nonce_different()
    {
        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", $this->get(route('login'))->headers->get('Content-Security-Policy'), $first);
        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", $this->get(route('login'))->headers->get('Content-Security-Policy'), $second);

        $this->assertNotSame($first[1], $second[1]);
    }

    public function test_la_reponse_porte_les_autres_en_tetes_de_securite()
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=(self)', $response->headers->get('Permissions-Policy'));
    }

    public function test_hsts_absent_sur_une_requete_non_securisee()
    {
        $response = $this->get(route('login'));

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_present_sur_une_requete_securisee()
    {
        // Une URL en `https://` : c'est son schema qui rend la requete securisee. Une variable
        // `HTTPS` ne suffit pas, le client de test prefixe tout chemin par `app.url` (`http://`)
        // et Symfony recalcule alors le schema a partir de l'URL.
        $response = $this->get(preg_replace('#^http://#', 'https://', route('login')));

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
