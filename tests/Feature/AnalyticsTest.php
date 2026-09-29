<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Mesure d'audience Google Analytics (README, « Mesure d'audience ») : sur les seules pages
 * commerciales (accueil, vitrine), jamais sur le back-office ni le parcours invite, dont les
 * adresses portent des organisations, des jetons de reprise et des signatures. Sans identifiant
 * configure, rien n'est charge et la CSP reste fermee a Google.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const MeasurementId = 'G-TEST123456';

    public function test_sans_identifiant_rien_n_est_transmis_et_la_csp_reste_fermee(): void
    {
        config(['services.google_analytics.measurement_id' => null]);

        $response = $this->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page->where('analyticsId', null));
        $this->assertStringNotContainsString('googletagmanager', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_avec_identifiant_l_accueil_et_la_vitrine_le_recoivent(): void
    {
        config(['services.google_analytics.measurement_id' => self::MeasurementId]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('analyticsId', self::MeasurementId));

        $this->get(route('showcase.index'))
            ->assertInertia(fn (Assert $page) => $page->where('analyticsId', self::MeasurementId));
    }

    public function test_avec_identifiant_la_csp_autorise_google_analytics(): void
    {
        config(['services.google_analytics.measurement_id' => self::MeasurementId]);

        $csp = (string) $this->get(route('home'))->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression('/script-src [^;]*https:\/\/www\.googletagmanager\.com/', $csp);
        $this->assertMatchesRegularExpression('/connect-src [^;]*https:\/\/\*\.google-analytics\.com/', $csp);
    }

    public function test_le_back_office_ne_recoit_jamais_l_identifiant(): void
    {
        config(['services.google_analytics.measurement_id' => self::MeasurementId]);

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->actingAs($owner)
            ->get(route('dashboard', ['current_tenant' => $tenant->slug]))
            ->assertInertia(fn (Assert $page) => $page->missing('analyticsId'));
    }
}
