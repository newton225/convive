<?php

namespace Tests\Feature;

use App\Support\Release;
use Illuminate\Support\Facades\File;
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
            'logging.channels.stack.channels' => ['daily'],
            'logging.channels.daily.level' => 'warning',
            'services.turnstile' => ['site_key' => '0x4AAAAAAAAbCdEfGhIjKlMn', 'secret_key' => '0x4AAAAAAAAbCdEfGhIjKlMnOpQrStUvWxYz'],
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
            // Une livraison estampillee (`convive:release`), dans un fichier propre au test.
            'convive.release_path' => $this->releasePath(),
        ]);

        Release::stamp('0741e99');
    }

    private function releasePath(): string
    {
        return storage_path('framework/testing/release-production-check.json');
    }

    protected function tearDown(): void
    {
        File::delete($this->releasePath());

        parent::tearDown();
    }

    public function test_une_livraison_non_estampillee_fait_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        File::delete($this->releasePath());

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('convive:release')
            ->assertFailed();
    }

    public function test_les_cles_d_essai_cloudflare_font_echouer_la_verification(): void
    {
        // Les cles d'essai acceptent tout le monde, robots compris, et affichent un bandeau rouge
        // « a des fins de test » aux invites.
        $this->safeConfiguration();
        config(['services.turnstile' => ['site_key' => '1x00000000000000000000AA', 'secret_key' => '1x0000000000000000000000000000000AA']]);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('TURNSTILE')
            ->assertFailed();
    }

    public function test_sans_cles_cloudflare_la_verification_echoue(): void
    {
        // Sans cles, la protection anti-robot, active par defaut sur chaque evenement, ne
        // s'applique nulle part.
        $this->safeConfiguration();
        config(['services.turnstile' => ['site_key' => null, 'secret_key' => null]]);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('TURNSTILE')
            ->assertFailed();
    }

    public function test_un_journal_sans_rotation_fait_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        config(['logging.channels.stack.channels' => ['single']]);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('LOG_STACK')
            ->assertFailed();
    }

    public function test_un_journal_au_niveau_debug_fait_echouer_la_verification(): void
    {
        $this->safeConfiguration();
        config(['logging.channels.daily.level' => 'debug']);

        $this->artisan('convive:production-check')
            ->expectsOutputToContain('LOG_LEVEL')
            ->assertFailed();
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
