<?php

namespace Tests\Feature\PaymentProofs;

use App\Actions\PaymentProofs\SubmitPaymentProof;
use App\Actions\Tenants\CreateTenant;
use App\Enums\PaymentChannel;
use App\Enums\RegistrationStatus;
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

class SubmitPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaRoot;

    private string $proofsRoot;

    /**
     * Meme isolation que `Tests\Feature\Tenants\BrandFileTest` : une racine temporaire plutot
     * que `Storage::fake()`, qui perdrait `serve => true`. `payment_proofs` en plus de
     * `tenant_media` depuis SECURITY.md H1 : les preuves ne vivent plus sur le meme disque que
     * les fichiers de marque.
     */
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

        // `forgetDisk()` jette l'instance sur laquelle `AppServiceProvider::boot()` avait pose
        // `serveUsing()` (piece jointe, SECURITY.md H1) : la reposer sur la nouvelle instance.
        AppServiceProvider::configurePaymentProofDisk();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaRoot);
        File::deleteDirectory($this->proofsRoot);

        parent::tearDown();
    }

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function eventOf(Tenant $tenant, array $attributes = []): Event
    {
        return $tenant->asCurrent(fn () => Event::factory()->open()->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function heldRegistration(Tenant $tenant, Event $event, array $attributes = []): Registration
    {
        return $tenant->asCurrent(fn () => Registration::factory()->held()->create([
            'event_id' => $event->id,
            ...$attributes,
        ]));
    }

    private function paymentAccountOf(Tenant $tenant): PaymentAccount
    {
        return $tenant->asCurrent(fn () => PaymentAccount::factory()->create());
    }

    /**
     * Une capture reelle a moitie noire et a moitie blanche, pas le carre uni que produit
     * `UploadedFile::fake()->image()` (`imagecreatetruecolor()` sans remplissage) : deux
     * fonds unis, quelles que soient leurs dimensions, se reduisent a la meme empreinte
     * moyenne. Le decoupage (horizontal ou vertical) donne deux empreintes reellement
     * distinctes, ce qu'il faut pour prouver qu'un vrai doublon d'image se distingue d'un
     * simple recu different.
     */
    private function patternedImage(string $name, string $split): UploadedFile
    {
        $size = 64;
        $image = imagecreatetruecolor($size, $size);
        $black = imagecolorallocate($image, 0, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        if ($split === 'horizontal') {
            imagefilledrectangle($image, 0, 0, $size - 1, (int) ($size / 2) - 1, $black);
            imagefilledrectangle($image, 0, (int) ($size / 2), $size - 1, $size - 1, $white);
        } else {
            imagefilledrectangle($image, 0, 0, (int) ($size / 2) - 1, $size - 1, $black);
            imagefilledrectangle($image, (int) ($size / 2), 0, $size - 1, $size - 1, $white);
        }

        ob_start();
        imagejpeg($image, null, 90);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        $path = tempnam(sys_get_temp_dir(), 'proof').'.jpg';
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{payment_account_id: int, channel: string, reference: ?string, guest_note: ?string}
     */
    private function proofData(PaymentAccount $account, array $overrides = []): array
    {
        return [
            'payment_account_id' => $account->id,
            'channel' => PaymentChannel::Wave->value,
            'reference' => 'WAVE-'.fake()->numerify('########'),
            'guest_note' => null,
            ...$overrides,
        ];
    }

    public function test_le_depot_fait_passer_l_inscription_en_preuve_soumise(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $this->heldRegistration($tenant, $event);
        $account = $this->paymentAccountOf($tenant);

        $proof = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $registration,
            $this->proofData($account),
            UploadedFile::fake()->image('recu.jpg'),
            (string) Str::uuid(),
        ));

        $this->assertNotNull($proof);
        $this->assertSame(RegistrationStatus::ProofSubmitted, $tenant->asCurrent(fn () => $registration->fresh())->status);
        $this->assertNotNull($tenant->asCurrent(fn () => $proof->fresh()->getFirstMedia(PaymentProof::ReceiptCollection)));
    }

    /**
     * README 2.1 : « L'envoi de preuve doit être refusé dans cet état [Expired] ». Le decompte
     * est ecoule mais le statut stocke est encore `Held` : la tache planifiee (README 2.4) n'est
     * pas encore passee. La garantie ne doit donc pas reposer sur le seul statut lu.
     */
    public function test_refuse_une_preuve_deposee_apres_expiration_du_decompte(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $this->heldRegistration($tenant, $event, ['held_until' => now()->subMinute()]);
        $account = $this->paymentAccountOf($tenant);

        $proof = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $registration,
            $this->proofData($account),
            UploadedFile::fake()->image('recu.jpg'),
            (string) Str::uuid(),
        ));

        $this->assertNull($proof);
        $this->assertSame(RegistrationStatus::Held, $tenant->asCurrent(fn () => $registration->fresh())->status);
    }

    public function test_refuse_une_preuve_sur_une_inscription_deja_confirmee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));
        $account = $this->paymentAccountOf($tenant);

        $proof = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $registration,
            $this->proofData($account),
            UploadedFile::fake()->image('recu.jpg'),
            (string) Str::uuid(),
        ));

        $this->assertNull($proof);
    }

    public function test_un_rejeu_avec_la_meme_cle_d_idempotence_ne_cree_pas_une_seconde_preuve(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $this->heldRegistration($tenant, $event);
        $account = $this->paymentAccountOf($tenant);
        $key = (string) Str::uuid();

        $first = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $registration, $this->proofData($account), UploadedFile::fake()->image('recu.jpg'), $key,
        ));
        $second = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $registration, $this->proofData($account), UploadedFile::fake()->image('autre.jpg'), $key,
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $tenant->asCurrent(fn () => PaymentProof::count()));
    }

    public function test_l_empreinte_perceptuelle_est_calculee_a_la_reception(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $this->heldRegistration($tenant, $event);
        $account = $this->paymentAccountOf($tenant);

        $proof = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $registration, $this->proofData($account), UploadedFile::fake()->image('recu.jpg'), (string) Str::uuid(),
        ));

        $this->assertNotNull($proof->perceptual_hash);
        $this->assertSame(16, strlen($proof->perceptual_hash));
    }

    public function test_deux_preuves_avec_la_meme_reference_sont_signalees_comme_doublon(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        [$first, $second] = $tenant->asCurrent(fn () => [
            PaymentProof::factory()->withReference('WAVE-12345678')->create(),
            PaymentProof::factory()->withReference('WAVE-12345678')->create(),
        ]);

        $this->assertTrue($tenant->asCurrent(fn () => $first->hasDuplicateReference()));
        $this->assertTrue($tenant->asCurrent(fn () => $second->hasDuplicateReference()));
    }

    public function test_une_reference_isolee_n_est_pas_signalee_comme_doublon(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $proof = $tenant->asCurrent(fn () => PaymentProof::factory()->withReference('WAVE-UNIQUE')->create());

        $this->assertFalse($tenant->asCurrent(fn () => $proof->hasDuplicateReference()));
    }

    public function test_le_meme_recu_depose_deux_fois_est_signale_comme_doublon_d_image(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $account = $this->paymentAccountOf($tenant);

        $receipt = $this->patternedImage('recu.jpg', 'horizontal');

        $first = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $this->heldRegistration($tenant, $event),
            $this->proofData($account, ['reference' => 'WAVE-AAA']),
            $receipt,
            (string) Str::uuid(),
        ));
        $second = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $this->heldRegistration($tenant, $event),
            $this->proofData($account, ['reference' => 'WAVE-BBB']),
            $receipt,
            (string) Str::uuid(),
        ));

        $this->assertTrue($tenant->asCurrent(fn () => $first->fresh()->hasDuplicateImage()));
        $this->assertTrue($tenant->asCurrent(fn () => $second->fresh()->hasDuplicateImage()));
    }

    public function test_deux_recus_differents_ne_sont_pas_signales_comme_doublon_d_image(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $account = $this->paymentAccountOf($tenant);

        $first = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $this->heldRegistration($tenant, $event),
            $this->proofData($account, ['reference' => 'WAVE-AAA']),
            $this->patternedImage('recu-un.jpg', 'horizontal'),
            (string) Str::uuid(),
        ));
        $second = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $this->heldRegistration($tenant, $event),
            $this->proofData($account, ['reference' => 'WAVE-BBB']),
            $this->patternedImage('recu-deux.jpg', 'vertical'),
            (string) Str::uuid(),
        ));

        $this->assertFalse($tenant->asCurrent(fn () => $first->fresh()->hasDuplicateImage()));
        $this->assertFalse($tenant->asCurrent(fn () => $second->fresh()->hasDuplicateImage()));
    }

    public function test_les_recus_ne_sont_pas_serves_depuis_la_racine_web(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $this->heldRegistration($tenant, $event);
        $account = $this->paymentAccountOf($tenant);

        $proof = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $registration, $this->proofData($account), UploadedFile::fake()->image('recu.jpg'), (string) Str::uuid(),
        ));

        $media = $tenant->asCurrent(fn () => $proof->fresh()->getFirstMedia(PaymentProof::ReceiptCollection));

        // La resolution du chemin, elle, ne doit pas dependre d'une tenancy encore active
        // (voir `TenantMediaPathGenerator`) : une page qui affiche l'URL d'un recu peut le
        // faire apres la fin de la requete qui l'a resolu.
        // Disque distinct de `tenant_media` (SECURITY.md H1) : une capture deposee par un
        // invite inconnu ne partage pas l'espace des fichiers de marque du locataire.
        $this->assertSame('payment_proofs', $media->disk);
        $this->assertFileDoesNotExist(public_path($media->getPathRelativeToRoot()));
    }

    public function test_l_url_du_recu_est_signee_et_expire_rapidement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);
        $registration = $this->heldRegistration($tenant, $event);
        $account = $this->paymentAccountOf($tenant);

        $proof = $tenant->asCurrent(fn () => app(SubmitPaymentProof::class)->handle(
            $registration, $this->proofData($account), UploadedFile::fake()->image('recu.jpg'), (string) Str::uuid(),
        ));

        $url = $tenant->asCurrent(fn () => $proof->fresh()->receiptUrl());

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
    }
}
