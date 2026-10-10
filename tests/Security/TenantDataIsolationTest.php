<?php

namespace Tests\Security;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\GuestClaim;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Cloisonnement des DONNEES (et pas seulement des acces) : une organisation A dont le proprietaire
 * parcourt toutes ses pages ne lit, nulle part, une donnee de l'organisation B. Chaque ressource de B
 * porte une marque reconnaissable ; on la cherche dans le corps de chaque reponse destinee a A, y
 * compris dans les donnees JSON que l'interface charge (props Inertia) et dans les exports.
 */
class TenantDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const Mark = 'ZXQ-SECRET-ORG-B';

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $ownerA;

    private Event $eventA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerA = User::factory()->withTwoFactor()->create();
        $ownerB = User::factory()->withTwoFactor()->create();

        $this->tenantA = app(CreateTenant::class)->handle($this->ownerA, 'Association Alpha');
        $this->tenantB = app(CreateTenant::class)->handle($ownerB, self::Mark.' Association');

        $this->eventA = $this->tenantA->asCurrent(function () {
            $event = Event::factory()->published()->create(['name' => 'Evenement Alpha']);
            Registration::factory()->proofSubmitted()->create(['event_id' => $event->id, 'name' => 'Invite Alpha']);

            return $event;
        });

        $this->tenantB->asCurrent(function () {
            $event = Event::factory()->published()->create(['name' => self::Mark.' Evenement', 'venue' => self::Mark.' Lieu']);
            $registration = Registration::factory()->proofSubmitted()->create([
                'event_id' => $event->id,
                'name' => self::Mark.' Invite',
                'phone' => '+2250799'.random_int(100000, 999999),
                'email' => 'zxq-secret-b@example.com',
            ]);
            PaymentProof::factory()->create(['registration_id' => $registration->id, 'reference' => self::Mark.'-REF']);
            GuestClaim::factory()->create(['registration_id' => $registration->id, 'message' => self::Mark.' reclamation confidentielle']);
            PaymentAccount::factory()->create(['label' => self::Mark.' Compte', 'account_number' => '+2250777000000']);
            Unit::factory()->create(['name' => self::Mark.' Unite']);
        });
    }

    /**
     * @return array<int, Route>
     */
    private function readableRoutes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            fn (Route $route) => is_string($route->getName())
                && str_starts_with($route->getName(), 'tenants.')
                && in_array('GET', $route->methods(), true)
                && in_array('tenant', $route->parameterNames(), true),
        ));
    }

    public function test_aucune_page_de_l_organisation_a_ne_contient_une_donnee_de_l_organisation_b(): void
    {
        $this->actingAs($this->ownerA);
        $leaks = [];
        $checked = 0;

        foreach ($this->readableRoutes() as $route) {
            $parameters = [];

            foreach ($route->parameterNames() as $name) {
                $parameters[$name] = match ($name) {
                    'tenant' => $this->tenantA->slug,
                    'event' => $this->eventA->id,
                    default => 1,
                };
            }

            $path = route($route->getName(), $parameters, false);

            foreach (['', '?filter[search]='.urlencode(self::Mark)] as $query) {
                $response = $this->call('GET', $path.$query, [], [], [], ['HTTP_X-Inertia' => 'true', 'HTTP_ACCEPT' => 'application/json']);
                $checked++;

                if (str_contains((string) $response->getContent(), self::Mark) || str_contains((string) $response->getContent(), 'zxq-secret-b@example.com')) {
                    $leaks[] = "{$route->getName()}{$query} -> fuite (statut {$response->getStatusCode()})";
                }
            }
        }

        $this->assertGreaterThan(60, $checked, 'Le balayage doit couvrir toutes les pages de lecture.');
        $this->assertSame([], $leaks, "Donnees de l'organisation B visibles par A :\n".implode("\n", $leaks));
    }

    public function test_la_recherche_de_l_organisation_a_ne_trouve_pas_les_inscrits_de_b(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->get(route('tenants.events.registrations.index', [$this->tenantA, $this->eventA, 'filter' => ['search' => self::Mark]]));

        $response->assertOk();
        $this->assertStringNotContainsString(self::Mark, (string) $response->getContent());
    }

    public function test_les_exports_de_l_organisation_a_ne_contiennent_rien_de_b(): void
    {
        $this->actingAs($this->ownerA);

        $csv = $this->get(route('tenants.events.registrations.export.csv', [$this->tenantA, $this->eventA]));

        $this->assertLessThan(500, $csv->getStatusCode());
        $this->assertStringNotContainsString(self::Mark, (string) $csv->streamedContent());
    }

    public function test_le_journal_et_les_notifications_de_a_ne_montrent_rien_de_b(): void
    {
        $this->actingAs($this->ownerA);

        foreach ([route('tenants.audit.index', $this->tenantA), route('notification-preferences.edit')] as $url) {
            $response = $this->get($url);

            $this->assertStringNotContainsString(self::Mark, (string) $response->getContent(), $url);
        }
    }
}
