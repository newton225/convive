<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Protection anti-robot du formulaire d'inscription (decision du proprietaire du projet,
 * 2026-10-03), par Cloudflare Turnstile : elle remplace le code par SMS, mis de cote, contre les
 * robots qui rempliraient le formulaire pour bloquer toutes les places (SECURITY.md C3). Reglage
 * par evenement, active par defaut ; sans cles Cloudflare, rien n'est demande.
 */
class BotCheckTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'registration_number' => 'CI-ABJ-2021-B-04812',
            'tax_number' => '1804517 K',
            'address' => 'Rue des Jardins',
            'city' => 'Abidjan',
            'country' => 'CI',
            'phone' => '+225 07 07 12 34 56',
        ])->save();
        $tenant->update(['subdomain' => 'convive-ci']);
        $this->tenant = $tenant->fresh();

        config(['services.turnstile' => ['site_key' => 'cle-publique', 'secret_key' => 'cle-secrete']]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function event(array $attributes = []): Event
    {
        return $this->tenant->asCurrent(function () use ($attributes) {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create($attributes);
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return $event->fresh();
        });
    }

    private function url(Event $event, string $suffix = ''): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return ($appUrl['scheme'] ?? 'http').'://convive-ci.'.config('convive.public_domain').$port
            .'/e/'.$event->public_token.$suffix;
    }

    /**
     * @param  array<string, string>  $extra
     * @return TestResponse<Response>
     */
    private function register(Event $event, array $extra = []): TestResponse
    {
        return $this->post($this->url($event, '/register'), [
            'name' => 'Aya Kouassi',
            'phone' => '+225 07 07 12 34 56',
            'unit_id' => $this->tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id')),
            'companions' => [],
            ...$extra,
        ]);
    }

    private function registrations(): int
    {
        return $this->tenant->asCurrent(fn () => Registration::count());
    }

    private function cloudflareAnswers(bool $success): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $success])]);
    }

    public function test_la_protection_est_activee_par_defaut_sur_un_nouvel_evenement(): void
    {
        $this->assertTrue($this->tenant->asCurrent(
            fn () => Event::factory()->published()->create()->fresh()->rule_bot_protection,
        ));
    }

    public function test_sans_verification_l_inscription_est_refusee(): void
    {
        $this->register($this->event())->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame(0, $this->registrations());
    }

    public function test_une_verification_reussie_laisse_passer_l_inscription(): void
    {
        $this->cloudflareAnswers(true);

        $this->register($this->event(), ['cf-turnstile-response' => 'jeton-du-navigateur'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->registrations());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'cle-secrete'
            && $request['response'] === 'jeton-du-navigateur');
    }

    public function test_une_verification_refusee_par_cloudflare_bloque_l_inscription(): void
    {
        $this->cloudflareAnswers(false);

        $this->register($this->event(), ['cf-turnstile-response' => 'jeton-invalide'])->assertSessionHasErrors('cf-turnstile-response');

        $this->assertSame(0, $this->registrations());
    }

    public function test_cloudflare_injoignable_n_empeche_pas_les_inscriptions(): void
    {
        // Une panne chez Cloudflare ne doit pas fermer les inscriptions de tous les evenements :
        // les autres protections (une reservation par numero, plafonds par adresse IP) restent.
        Http::fake(['challenges.cloudflare.com/*' => Http::failedConnection()]);

        $this->register($this->event(), ['cf-turnstile-response' => 'jeton-du-navigateur'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->registrations());
    }

    public function test_l_organisateur_peut_desactiver_la_protection(): void
    {
        $this->register($this->event(['rule_bot_protection' => false]))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->registrations());
        Http::assertNothingSent();
    }

    public function test_sans_cles_cloudflare_rien_n_est_demande(): void
    {
        config(['services.turnstile' => ['site_key' => null, 'secret_key' => null]]);

        $this->register($this->event())->assertSessionHasNoErrors();

        $this->assertSame(1, $this->registrations());
    }

    public function test_le_formulaire_recoit_la_cle_publique_seulement_quand_la_protection_s_applique(): void
    {
        $protected = $this->event();
        $open = $this->event(['rule_bot_protection' => false]);

        $this->get($this->url($protected, '/register'))
            ->assertInertia(fn (Assert $page) => $page->where('botCheckSiteKey', 'cle-publique'));

        $this->get($this->url($open, '/register'))
            ->assertInertia(fn (Assert $page) => $page->where('botCheckSiteKey', null));
    }

    public function test_la_securite_des_pages_n_autorise_cloudflare_que_si_les_cles_sont_reglees(): void
    {
        $csp = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression('#script-src[^;]*https://challenges\.cloudflare\.com#', $csp);
        $this->assertMatchesRegularExpression('#frame-src[^;]*https://challenges\.cloudflare\.com#', $csp);

        config(['services.turnstile' => ['site_key' => null, 'secret_key' => null]]);

        $this->assertStringNotContainsString('challenges.cloudflare.com', (string) $this->get(route('login'))->headers->get('Content-Security-Policy'));
    }
}
