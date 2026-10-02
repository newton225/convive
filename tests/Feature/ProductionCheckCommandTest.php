<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Verification de configuration avant mise en production (SECURITY.md, « Erreurs » et grille de
 * test) : `APP_DEBUG=false` et consorts verifies par une commande a jouer au deploiement, plutot
 * que supposes.
 */
class ProductionCheckCommandTest extends TestCase
{
    private function safeConfiguration(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'session.secure' => true,
            'session.http_only' => true,
            'mail.default' => 'resend',
            'convive.two_factor.enforced' => true,
            'convive.security.trusted_proxies' => '10.0.0.1',
            'convive.legal' => [
                'editor_name' => 'Convive SARL',
                'legal_form' => 'SARL',
                'share_capital' => '1 000 000 F CFA',
                'registration_number' => 'CI-ABJ-2026-B-00001',
                'tax_number' => '2600001A',
                'editor_address' => 'Cocody, Abidjan',
                'publication_director' => 'Amara Kone',
                'contact_email' => 'contact@convive.test',
                'privacy_email' => 'donnees@convive.test',
                'host_name' => 'Hebergeur SA',
                'host_address' => 'Abidjan',
                'mail_provider' => 'Resend',
                'reviewed' => true,
            ],
        ]);
    }

    public function test_une_identite_d_editeur_incomplete_fait_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        config(['convive.legal.registration_number' => null]);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('registration_number')
            ->assertFailed();
    }

    public function test_des_textes_juridiques_non_valides_font_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        config(['convive.legal.reviewed' => false]);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('CONVIVE_LEGAL_REVIEWED')
            ->assertFailed();
    }

    public function test_une_configuration_saine_passe(): void
    {
        $this->safeConfiguration();

        $this->artisan('convive:production-check')->assertSuccessful();
    }

    public function test_le_mode_debug_fait_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        config(['app.debug' => true]);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('APP_DEBUG')
            ->assertFailed();
    }

    public function test_un_cookie_de_session_non_securise_fait_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        config(['session.secure' => null]);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE')
            ->assertFailed();
    }

    public function test_faire_confiance_a_tous_les_proxys_fait_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        config(['convive.security.trusted_proxies' => '*']);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('TRUSTED_PROXIES')
            ->assertFailed();
    }

    public function test_les_emails_ecrits_dans_le_journal_font_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        config(['mail.default' => 'log']);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('MAIL_MAILER')
            ->assertFailed();
    }

    public function test_la_double_authentification_desactivee_fait_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        config(['convive.two_factor.enforced' => false]);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('CONVIVE_ENFORCE_TWO_FACTOR')
            ->assertFailed();
    }
}
