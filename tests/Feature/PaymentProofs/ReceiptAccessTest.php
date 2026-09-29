<?php

namespace Tests\Feature\PaymentProofs;

use App\Actions\PaymentProofs\SubmitPaymentProof;
use App\Actions\Tenants\CreateTenant;
use App\Enums\PaymentChannel;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Consultation d'un recu depuis la file de preuves (SECURITY.md H2) : une route authentifiee qui
 * diffuse le fichier et journalise l'acces avec son acteur, plutot qu'une URL signee anonyme qui
 * resterait utilisable par quiconque la recoit.
 */
class ReceiptAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaRoot;

    private string $proofsRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaRoot = storage_path('framework/testing/tenant-media-'.Str::random(8));
        $this->proofsRoot = storage_path('framework/testing/payment-proofs-'.Str::random(8));

        config([
            'filesystems.disks.tenant_media.root' => $this->mediaRoot,
            'filesystems.disks.payment_proofs.root' => $this->proofsRoot,
        ]);
        Storage::forgetDisk('tenant_media');
        Storage::forgetDisk('payment_proofs');
        AppServiceProvider::configurePaymentProofDisk();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaRoot);
        File::deleteDirectory($this->proofsRoot);

        parent::tearDown();
    }

    /**
     * @return array{tenant: Tenant, owner: User, event: Event, proof: PaymentProof}
     */
    private function submittedProof(): array
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        [$event, $proof] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->held()->create(['event_id' => $event->id]);
            $account = PaymentAccount::factory()->create();

            $proof = app(SubmitPaymentProof::class)->handle($registration, [
                'payment_account_id' => $account->id,
                'channel' => PaymentChannel::Wave->value,
                'reference' => 'WAVE-12345678',
                'amount_declared' => 15000,
            ], UploadedFile::fake()->image('recu.jpg'), (string) Str::uuid());

            return [$event, $proof];
        });

        return ['tenant' => $tenant, 'owner' => $owner, 'event' => $event, 'proof' => $proof];
    }

    public function test_un_membre_autorise_telecharge_le_recu_en_piece_jointe_sans_cache(): void
    {
        ['tenant' => $tenant, 'owner' => $owner, 'event' => $event, 'proof' => $proof] = $this->submittedProof();

        $response = $this->actingAs($owner)
            ->get(route('tenants.events.proofs.receipt', [$tenant, $event, $proof]))
            ->assertOk();

        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        // La CSP propre au recu survit a celle de toute l'application, posee apres coup.
        $this->assertSame('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function test_chaque_consultation_d_un_recu_est_journalisee_avec_l_acteur(): void
    {
        ['tenant' => $tenant, 'owner' => $owner, 'event' => $event, 'proof' => $proof] = $this->submittedProof();

        $this->actingAs($owner)->get(route('tenants.events.proofs.receipt', [$tenant, $event, $proof]));

        $tenant->asCurrent(function () use ($owner, $proof) {
            $this->assertDatabaseHas('activity_log', [
                'description' => 'payment_proof.receipt_viewed',
                'subject_type' => $proof->getMorphClass(),
                'subject_id' => $proof->id,
                'causer_id' => $owner->id,
            ]);
        });
    }

    public function test_un_membre_sans_la_permission_des_preuves_est_refuse(): void
    {
        ['tenant' => $tenant, 'event' => $event, 'proof' => $proof] = $this->submittedProof();

        $agent = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $agent, [TenantPermission::ScanPerform]);

        $this->actingAs($agent)
            ->get(route('tenants.events.proofs.receipt', [$tenant, $event, $proof]))
            ->assertForbidden();
    }

    public function test_un_recu_presente_sous_un_autre_evenement_repond_404(): void
    {
        ['tenant' => $tenant, 'owner' => $owner, 'proof' => $proof] = $this->submittedProof();
        $otherEvent = $tenant->asCurrent(fn () => Event::factory()->open()->create());

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.receipt', [$tenant, $otherEvent, $proof]))
            ->assertNotFound();
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        ['tenant' => $tenant, 'event' => $event, 'proof' => $proof] = $this->submittedProof();

        $stranger = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($stranger, 'Autre organisation');

        $this->actingAs($stranger)
            ->get(route('tenants.events.proofs.receipt', [$tenant, $event, $proof]))
            ->assertNotFound();
    }

    public function test_la_file_de_preuves_pointe_vers_la_route_authentifiee(): void
    {
        ['tenant' => $tenant, 'owner' => $owner, 'event' => $event, 'proof' => $proof] = $this->submittedProof();

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertInertia(fn ($page) => $page->where(
                'rows.0.receiptUrl',
                route('tenants.events.proofs.receipt', [$tenant, $event, $proof], absolute: false),
            ));
    }
}
