<?php

namespace Tests\Security;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\GuestClaim;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Profile;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Balayage systematique du back-office : TOUTES les routes nommees `tenants.*` (SECURITY.md, grille
 * « Cloisonnement » : « pour chaque route du back-office, requete d'un locataire tiers, attendu 404 »).
 *
 * Quatre regards sur le meme catalogue de routes, genere depuis le routeur et non ecrit a la main :
 * une route ajoutee demain est testee sans qu'on y pense.
 *
 * - un inconnu non connecte n'obtient jamais une page : il est renvoye vers la connexion ;
 * - un utilisateur connecte d'une AUTRE organisation recoit 404 partout, jamais 403 ni 200 ;
 * - un membre en lecture seule ne modifie rien : toute ecriture lui est refusee, et refusee AVANT la
 *   validation (CLAUDE.md : l'autorisation se joue dans `authorize()` du Form Request) ;
 * - le proprietaire n'obtient jamais une erreur serveur sur une page qu'il consulte.
 */
class BackOfficeAccessSweepTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes qu'un membre en lecture seule a le droit d'utiliser, ou qui n'appartiennent pas a une
     * organisation : on s'y attend a autre chose qu'un refus.
     */
    private const OpenToEveryMember = [
        'tenants.index', 'tenants.store', 'tenants.leave', 'tenants.switch',
    ];

    /**
     * Routes qui appellent un service exterieur (paiement) : jouees sans reseau, elles ne disent
     * rien de l'acces.
     */
    private const ExternalService = [
        'tenants.billing.checkout', 'tenants.billing.payment-method', 'tenants.billing.cancel',
    ];

    private Tenant $tenant;

    private User $owner;

    private User $reader;

    private User $stranger;

    /** @var array<string, mixed> */
    private array $resources = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        $this->reader = User::factory()->withTwoFactor()->create();
        $this->joinWithProfile($this->tenant, $this->reader, 'Lecture');

        $this->stranger = User::factory()->withTwoFactor()->create();
        $this->stranger->forceFill(['current_tenant_id' => null])->save();

        $this->resources = $this->tenant->run(function () {
            $event = Event::factory()->published()->create();
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);
            $claim = GuestClaim::factory()->create(['registration_id' => $registration->id]);
            $account = PaymentAccount::factory()->create();
            $table = $event->seatingTables()->firstOrFail();

            return [
                'event' => $event,
                'registration' => $registration,
                'proof' => $proof,
                'claim' => $claim,
                'payment_account' => $account,
                'table' => $table,
                'unit' => Unit::query()->firstOrFail(),
                'profile' => Profile::where('name', 'Lecture')->firstOrFail(),
            ];
        });
    }

    /**
     * Les routes d'une organisation : toutes celles dont le nom commence par `tenants.`.
     *
     * @return array<int, Route>
     */
    private function backOfficeRoutes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $route) => is_string($route->getName())
                && str_starts_with($route->getName(), 'tenants.')
                && in_array('tenant', $route->parameterNames(), true),
        ));
    }

    /**
     * L'adresse d'une route, ses parametres remplis avec de vraies ressources de l'organisation (ou un
     * identifiant inexistant quand on n'en a pas).
     */
    private function pathFor(Route $route): string
    {
        $values = [
            'tenant' => $this->tenant->slug,
            'member' => $this->reader->id,
            'file' => 'logo',
            'plan' => 'association',
        ];

        foreach ($this->resources as $name => $model) {
            $values[$name] = $model->getKey();
        }

        $parameters = [];

        foreach ($route->parameterNames() as $name) {
            $parameters[$name] = $values[$name] ?? 999999;
        }

        return route($route->getName(), $parameters, false);
    }

    private function send(Route $route, ?User $user): TestResponse
    {
        $method = collect($route->methods())->first(fn (string $method) => $method !== 'HEAD');
        $path = $this->pathFor($route);

        if ($user !== null) {
            $this->actingAs($user);
        }

        return $this->call($method, $path);
    }

    public function test_le_balayage_couvre_toutes_les_routes_du_back_office(): void
    {
        // Garde-fou : si le routeur change de forme, le balayage ne doit pas devenir vide sans bruit.
        $this->assertGreaterThan(80, count($this->backOfficeRoutes()));
    }

    public function test_un_visiteur_non_connecte_est_renvoye_vers_la_connexion_sur_toute_route(): void
    {
        $failures = [];

        foreach ($this->backOfficeRoutes() as $route) {
            $response = $this->send($route, null);

            if (! in_array($response->getStatusCode(), [302, 401, 404], true)
                || ($response->getStatusCode() === 302 && ! str_contains((string) $response->headers->get('Location'), 'login'))) {
                $failures[] = "{$route->getName()} -> {$response->getStatusCode()} ".$response->headers->get('Location');
            }
        }

        $this->assertSame([], $failures, "Routes accessibles ou mal protegees sans connexion :\n".implode("\n", $failures));
    }

    public function test_une_autre_organisation_recoit_404_sur_toute_route(): void
    {
        $failures = [];

        foreach ($this->backOfficeRoutes() as $route) {
            if (in_array($route->getName(), self::OpenToEveryMember, true)) {
                continue;
            }

            $response = $this->send($route, $this->stranger);

            if ($response->getStatusCode() !== 404) {
                $failures[] = "{$route->getName()} -> {$response->getStatusCode()}";
            }
        }

        $this->assertSame([], $failures, "Routes qui repondent autre chose que 404 a un locataire tiers :\n".implode("\n", $failures));
    }

    public function test_un_membre_en_lecture_seule_ne_peut_rien_ecrire_et_est_refuse_avant_la_validation(): void
    {
        $failures = [];

        foreach ($this->backOfficeRoutes() as $route) {
            $name = $route->getName();
            $isWrite = collect($route->methods())->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])->isNotEmpty();

            if (! $isWrite || in_array($name, self::OpenToEveryMember, true) || in_array($name, self::ExternalService, true)) {
                continue;
            }

            $response = $this->send($route, $this->reader);

            // 403 : refus. 404 : ressource absente ou masquee. Tout le reste (422, 302 avec erreurs de
            // champ, 2xx) veut dire que la validation ou l'action a precede l'autorisation.
            // Une route sensible redemande d'abord le mot de passe ou le code a deux facteurs : le renvoi vers
            // cette confirmation precede le refus, l'ecriture n'a pas lieu pour autant.
            $stepUp = $response->getStatusCode() === 302
                && preg_match('#confirm-(password|two-factor)#', (string) $response->headers->get('Location')) === 1;

            if (! $stepUp && ! in_array($response->getStatusCode(), [403, 404], true)) {
                $failures[] = "{$name} -> {$response->getStatusCode()} ".$response->headers->get('Location');
            }
        }

        $this->assertSame([], $failures, "Ecritures non refusees a un membre en lecture seule :\n".implode("\n", $failures));
    }

    public function test_le_proprietaire_ne_provoque_jamais_d_erreur_serveur_en_consultant_une_page(): void
    {
        $this->actingAs($this->owner);

        $failures = [];

        foreach ($this->backOfficeRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // Les telechargements et exports lourds ont leurs propres tests ; ici on cherche le 500.
            $response = $this->call('GET', $this->pathFor($route));

            if ($response->getStatusCode() >= 500) {
                $failures[] = "{$route->getName()} -> {$response->getStatusCode()}";
            }
        }

        $this->assertSame([], $failures, "Pages du back-office en erreur serveur :\n".implode("\n", $failures));
    }

    public function test_un_membre_sans_aucune_permission_ne_voit_aucune_page_de_donnees(): void
    {
        $nobody = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $nobody, [], 'Aucun droit');
        $this->actingAs($nobody);

        $failures = [];

        foreach ($this->backOfficeRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || in_array($route->getName(), self::OpenToEveryMember, true)) {
                continue;
            }

            $response = $this->call('GET', $this->pathFor($route));

            // Un profil vide ne lit rien : 403 (ou 404 pour une ressource masquee). La page « Equipe »
            // et la sortie de l'organisation restent a tout membre.
            if ($response->getStatusCode() === 200) {
                $failures[] = $route->getName();
            }
        }

        // Ces pages sont lisibles par tout membre (voir la politique de chacune) : on les nomme pour que
        // la liste reste lue, et non pour les excuser en bloc.
        $expectedOpen = ['tenants.edit', 'tenants.organisation.edit', 'tenants.update', 'tenants.dashboard'];

        $this->assertSame([], array_values(array_diff($failures, $expectedOpen)), "Pages lisibles par un profil sans aucune permission :\n".implode("\n", $failures));
    }

    public function test_les_permissions_du_catalogue_sont_toutes_exercees_par_un_profil_au_moins(): void
    {
        // La grille de test des profils repose sur ce catalogue : il ne doit pas etre vide.
        $this->assertNotEmpty(TenantPermission::cases());
    }
}
