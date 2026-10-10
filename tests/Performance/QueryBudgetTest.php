<?php

namespace Tests\Performance;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Models\Event;
use App\Models\EventPriceCategory;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\RegistrationCompanion;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventBus;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Budget de requetes SQL des pages principales : le nombre de requetes ne doit PAS grandir avec le
 * nombre de lignes affichees (le defaut classique « N+1 »), et reste sous un plafond.
 *
 * Methode : on mesure la meme page avec peu de donnees, puis avec beaucoup, et on exige que le
 * nombre de requetes soit le meme a quelques unites pres. Une page qui interroge la base une fois par
 * inscription affichee (25 par page) le trahit immediatement. Les releves sont ecrits dans
 * `qa/results/queries.json` pour le rapport de tests.
 */
class QueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /** Un ecart de quelques requetes (cache, preferences) est normal ; au-dela, c'est du N+1. */
    private const Tolerance = 4;

    /** Plafond absolu par page : au-dela, la page fait trop de choses a la fois. */
    private const Ceiling = 90;

    private Tenant $tenant;

    private User $owner;

    private Event $event;

    /** @var array<string, array<string, int>> */
    private static array $measures = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

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

        $this->event = $this->tenant->asCurrent(function () {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create(['tables' => [60, 10]]);
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());
            EventPriceCategory::factory()->create(['event_id' => $event->id, 'name' => 'Standard', 'price' => 10000]);

            return $event->fresh();
        });
    }

    private function seedRegistrations(int $count): void
    {
        $this->tenant->asCurrent(function () use ($count) {
            $unit = Unit::query()->firstOrFail();
            $category = EventPriceCategory::query()->where('event_id', $this->event->id)->firstOrFail();

            foreach (range(1, $count) as $index) {
                $registration = Registration::factory()->create([
                    'event_id' => $this->event->id,
                    'status' => $index % 3 === 0 ? 'proof_submitted' : ($index % 3 === 1 ? 'confirmed' : 'held'),
                    'unit_id' => $unit->id,
                    'price_category_id' => $category->id,
                    'party_size' => $index % 4 === 0 ? 3 : 1,
                    'held_until' => now()->addHour(),
                ]);

                if ($index % 4 === 0) {
                    RegistrationCompanion::factory()->count(2)->create([
                        'registration_id' => $registration->id,
                        'unit_id' => $unit->id,
                        'price_category_id' => $category->id,
                    ]);
                }

                if ($index % 3 !== 2) {
                    PaymentProof::factory()->create(['registration_id' => $registration->id]);
                }
            }
        });
    }

    /**
     * @param  callable(): void  $request
     */
    private function queries(callable $request): int
    {
        $count = 0;

        EventBus::listen(QueryExecuted::class, function () use (&$count) {
            $count++;
        });

        $request();

        return $count;
    }

    /**
     * @return array<string, callable(): TestResponse>
     */
    private function pages(): array
    {
        $tenant = $this->tenant;
        $event = $this->event;
        $registration = fn () => $this->tenant->asCurrent(fn () => Registration::query()->where('event_id', $event->id)->where('status', 'held')->firstOrFail());

        return [
            'Liste des evenements' => fn () => $this->actingAs($this->owner)->get(route('tenants.events.index', $tenant)),
            'Tableau de bord' => fn () => $this->actingAs($this->owner)->get(route('dashboard', $tenant)),
            'Base d\'inscrits' => fn () => $this->actingAs($this->owner)->get(route('tenants.events.registrations.index', [$tenant, $event])),
            'File des preuves' => fn () => $this->actingAs($this->owner)->get(route('tenants.events.proofs.index', [$tenant, $event])),
            'Rapport de l\'evenement' => fn () => $this->actingAs($this->owner)->get(route('tenants.events.report.show', [$tenant, $event])),
            'Plan de salle' => fn () => $this->actingAs($this->owner)->get(route('tenants.events.seating.index', [$tenant, $event])),
            'Rapprochement' => fn () => $this->actingAs($this->owner)->get(route('tenants.events.reconciliation.index', [$tenant, $event])),
            'Page publique de l\'evenement' => fn () => $this->get('http://convive-ci.'.config('convive.public_domain').'/e/'.$event->public_token),
            'Page du dossier de l\'invite' => fn () => $this->get($registration()->signedResumeUrl()),
        ];
    }

    public function test_aucune_page_ne_fait_de_requetes_proportionnelles_au_nombre_de_lignes(): void
    {
        $failures = [];

        $this->seedRegistrations(6);
        $small = [];

        foreach ($this->pages() as $name => $request) {
            $small[$name] = $this->queries(fn () => $request()->assertOk());
        }

        $this->seedRegistrations(60);
        $large = [];

        foreach ($this->pages() as $name => $request) {
            $large[$name] = $this->queries(fn () => $request()->assertOk());
        }

        foreach ($small as $name => $count) {
            self::$measures[$name] = ['avec_6_inscrits' => $count, 'avec_66_inscrits' => $large[$name]];

            if ($large[$name] - $count > self::Tolerance) {
                $failures[] = "{$name} : {$count} requetes avec 6 inscrits, {$large[$name]} avec 66 (N+1 probable)";
            }

            if ($large[$name] > self::Ceiling) {
                $failures[] = "{$name} : {$large[$name]} requetes, au-dela du plafond de ".self::Ceiling;
            }
        }

        file_put_contents(
            base_path('qa/results/queries.json'),
            json_encode(['date' => now()->toIso8601String(), 'pages' => self::$measures], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $this->assertSame([], $failures, "Budget de requetes depasse :\n".implode("\n", $failures));
    }
}
