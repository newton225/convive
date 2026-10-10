<?php

namespace Tests\Performance;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\EventPriceCategory;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\RegistrationCompanion;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Temps de reponse et memoire des pages et exports avec un VRAI volume : 2 000 inscriptions, dont
 * 400 avec deux accompagnateurs, et 1 300 preuves. Les temps sont ceux du noyau applicatif (requete
 * traitee en processus, sans reseau), avec SQLite : ils servent de plafond de non-regression, pas de
 * promesse de production. Les releves vont dans `qa/results/timings.json`.
 */
class VolumeTimingTest extends TestCase
{
    use RefreshDatabase;

    private const Registrations = 2000;

    private User $owner;

    private $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        $this->event = $this->tenant->asCurrent(function () {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create(['tables' => [300, 10]]);
            $category = EventPriceCategory::factory()->create(['event_id' => $event->id, 'name' => 'Standard', 'price' => 10000]);
            $unit = Unit::query()->firstOrFail();

            foreach (range(1, self::Registrations) as $index) {
                $registration = Registration::factory()->create([
                    'event_id' => $event->id,
                    'status' => match ($index % 4) {
                        0 => 'confirmed',
                        1 => 'proof_submitted',
                        2 => 'held',
                        default => 'expired',
                    },
                    'unit_id' => $unit->id,
                    'price_category_id' => $category->id,
                    'party_size' => $index % 5 === 0 ? 3 : 1,
                    'held_until' => now()->addHour(),
                ]);

                if ($index % 5 === 0) {
                    RegistrationCompanion::factory()->count(2)->create([
                        'registration_id' => $registration->id,
                        'unit_id' => $unit->id,
                        'price_category_id' => $category->id,
                    ]);
                }

                if (in_array($index % 4, [0, 1], true)) {
                    PaymentProof::factory()->create(['registration_id' => $registration->id]);
                }
            }

            return $event->fresh();
        });
    }

    /**
     * @param  callable(): TestResponse  $request
     * @return array{seconds: float, megabytes: float, bytes: int, status: int}
     */
    private function timed(callable $request): array
    {
        gc_collect_cycles();
        $memoryBefore = memory_get_peak_usage(true);
        $started = hrtime(true);

        $response = $request();
        $content = $response->baseResponse instanceof StreamedResponse
            ? $response->streamedContent()
            : (string) $response->getContent();

        return [
            'seconds' => round((hrtime(true) - $started) / 1e9, 3),
            'megabytes' => round((memory_get_peak_usage(true) - $memoryBefore) / 1048576, 1),
            'bytes' => strlen($content),
            'status' => $response->getStatusCode(),
        ];
    }

    public function test_les_pages_et_exports_restent_rapides_avec_deux_mille_inscriptions(): void
    {
        $this->actingAs($this->owner);
        $tenant = $this->tenant;
        $event = $this->event;
        $json = [];

        // [libelle => [requete, plafond en secondes]]
        $cases = [
            'Base d\'inscrits, page 1' => [fn () => $this->call('GET', route('tenants.events.registrations.index', [$tenant, $event], false), [], [], [], $json), 3.0],
            'Base d\'inscrits, recherche' => [fn () => $this->call('GET', route('tenants.events.registrations.index', [$tenant, $event], false).'?filter[search]=martin', [], [], [], $json), 3.0],
            'Base d\'inscrits, derniere page' => [fn () => $this->call('GET', route('tenants.events.registrations.index', [$tenant, $event], false).'?page=999', [], [], [], $json), 3.0],
            'Base d\'inscrits, filtre a verifier' => [fn () => $this->call('GET', route('tenants.events.registrations.index', [$tenant, $event], false).'?filter[status]=proof_submitted', [], [], [], $json), 3.0],
            'File des preuves' => [fn () => $this->call('GET', route('tenants.events.proofs.index', [$tenant, $event], false), [], [], [], $json), 4.0],
            'Rapport de l\'evenement' => [fn () => $this->call('GET', route('tenants.events.report.show', [$tenant, $event], false), [], [], [], $json), 4.0],
            'Tableau de bord' => [fn () => $this->call('GET', route('dashboard', $tenant, false), [], [], [], $json), 3.0],
            'Plan de salle' => [fn () => $this->call('GET', route('tenants.events.seating.index', [$tenant, $event], false), [], [], [], $json), 4.0],
            'Export CSV (2 000 inscriptions)' => [fn () => $this->get(route('tenants.events.registrations.export.csv', [$tenant, $event])), 10.0],
            'Export Excel (2 000 inscriptions)' => [fn () => $this->get(route('tenants.events.registrations.export.excel', [$tenant, $event])), 25.0],
        ];

        $results = [];
        $failures = [];

        foreach ($cases as $label => [$request, $ceiling]) {
            $measure = $this->timed($request);
            $results[$label] = $measure + ['plafond_secondes' => $ceiling];

            if ($measure['status'] >= 400) {
                $failures[] = "{$label} : statut {$measure['status']}";
            }

            if ($measure['seconds'] > $ceiling) {
                $failures[] = "{$label} : {$measure['seconds']} s, au-dela du plafond de {$ceiling} s";
            }
        }

        file_put_contents(
            base_path('qa/results/timings.json'),
            json_encode(['date' => now()->toIso8601String(), 'inscriptions' => self::Registrations, 'mesures' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $this->assertSame([], $failures, "Performances degradees :\n".implode("\n", $failures));
    }
}
