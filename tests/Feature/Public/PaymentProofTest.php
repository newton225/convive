<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
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

class PaymentProofTest extends TestCase
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
        // `serveUsing()` (piece jointe, SECURITY.md H1) : la reposer sur la nouvelle instance,
        // sinon les tests d'en-tetes de cette classe passeraient pour la mauvaise raison.
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

    private function publishableTenant(User $owner, string $subdomain = 'convive-ci'): Tenant
    {
        $tenant = $this->tenantOwnedBy($owner);

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

        $tenant->update(['subdomain' => $subdomain]);

        $tenant->asCurrent(fn () => PaymentAccount::factory()->create());

        return $tenant->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedEvent(Tenant $tenant, array $attributes = []): Event
    {
        return $tenant->asCurrent(function () use ($attributes) {
            $event = Event::factory()->published()->create($attributes);
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return $event->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{registration: Registration, resume: string}
     */
    private function heldRegistration(Tenant $tenant, Event $event, array $attributes = []): array
    {
        $resume = Registration::generateResumeToken();

        $registration = $tenant->asCurrent(fn () => Registration::factory()->held()->create([
            'event_id' => $event->id,
            'resume_token_hash' => Registration::hashResumeToken($resume),
            ...$attributes,
        ]));

        return ['registration' => $registration, 'resume' => $resume];
    }

    private function urlFor(string $subdomain, string $token, string $suffix = ''): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $domain = $subdomain.'.'.config('convive.public_domain');

        return "{$scheme}://{$domain}{$port}/e/{$token}{$suffix}";
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(PaymentAccount $account): array
    {
        return [
            'payment_account_id' => $account->id,
            'channel' => PaymentChannel::Wave->value,
            'reference' => 'WAVE-'.fake()->numerify('########'),
            'amount_declared' => 15000,
            'idempotency_key' => (string) Str::uuid(),
            'receipt' => UploadedFile::fake()->image('recu.jpg', 200, 200),
        ];
    }

    public function test_une_preuve_valide_fait_passer_l_inscription_en_verification(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['registration' => $registration, 'resume' => $resume] = $this->heldRegistration($tenant, $event);

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $this->validPayload($account),
        )->assertRedirect($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}"));

        $this->assertSame(
            RegistrationStatus::ProofSubmitted,
            $tenant->asCurrent(fn () => $registration->fresh())->status,
        );
    }

    public function test_le_recu_se_sert_en_piece_jointe_jamais_en_affichage_direct(): void
    {
        // SECURITY.md H1 : une capture deposee par un invite inconnu ne s'ouvre jamais dans
        // l'onglet du navigateur sur le domaine principal, quel que soit son contenu reel.
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['resume' => $resume] = $this->heldRegistration($tenant, $event);

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $this->validPayload($account),
        );

        $receiptUrl = $tenant->asCurrent(fn () => PaymentProof::firstOrFail()->receiptUrl());

        $this->assertNotNull($receiptUrl);
        $this->assertStringContainsString('/payment-proofs/', $receiptUrl);

        $this->get($receiptUrl)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertDownload();
    }

    public function test_la_page_de_reservation_affiche_la_preuve_recue(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['resume' => $resume] = $this->heldRegistration($tenant, $event);

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $this->validPayload($account),
        );

        $this->get($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}"))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('registration.status', 'proof_submitted'));
    }

    public function test_le_compte_de_versement_est_obligatoire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['resume' => $resume] = $this->heldRegistration($tenant, $event);

        $payload = $this->validPayload($account);
        unset($payload['payment_account_id']);

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $payload,
        )->assertSessionHasErrors('payment_account_id');
    }

    public function test_un_compte_qui_n_appartient_pas_a_l_evenement_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        ['resume' => $resume] = $this->heldRegistration($tenant, $event);

        $foreignAccountId = $tenant->asCurrent(fn () => PaymentAccount::factory()->create()->id);

        $payload = $this->validPayload($tenant->asCurrent(fn () => PaymentAccount::first()));
        $payload['payment_account_id'] = $foreignAccountId;

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $payload,
        )->assertSessionHasErrors('payment_account_id');
    }

    public function test_la_reference_est_obligatoire_hors_versement_en_especes(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['resume' => $resume] = $this->heldRegistration($tenant, $event);

        $payload = $this->validPayload($account);
        unset($payload['reference']);

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $payload,
        )->assertSessionHasErrors('reference');
    }

    public function test_un_versement_en_especes_n_exige_pas_de_reference(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['resume' => $resume] = $this->heldRegistration($tenant, $event);

        // Le canal declare par l'invite pilote la regle (`PaymentChannel::hasAccountNumber()`),
        // independamment du canal du compte choisi : deux champs distincts (README ecran 5).
        $payload = $this->validPayload($account);
        $payload['channel'] = PaymentChannel::Cash->value;
        unset($payload['reference']);

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $payload,
        )->assertSessionDoesntHaveErrors('reference');
    }

    public function test_le_recu_doit_etre_une_image(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['resume' => $resume] = $this->heldRegistration($tenant, $event);

        $payload = $this->validPayload($account);
        $payload['receipt'] = UploadedFile::fake()->create('recu.pdf', 100, 'application/pdf');

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $payload,
        )->assertSessionHasErrors('receipt');
    }

    public function test_le_recu_ne_peut_pas_depasser_cinq_mega_octets(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['resume' => $resume] = $this->heldRegistration($tenant, $event);

        $payload = $this->validPayload($account);
        $payload['receipt'] = UploadedFile::fake()->image('lourd.jpg')->size(5121);

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $payload,
        )->assertSessionHasErrors('receipt');
    }

    public function test_une_preuve_deposee_apres_expiration_du_decompte_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['registration' => $registration, 'resume' => $resume] = $this->heldRegistration($tenant, $event, [
            'held_until' => now()->subMinute(),
        ]);

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof"),
            $this->validPayload($account),
        )
            ->assertRedirect($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}"))
            // Le refus se dit : sans ce message, l'invite croyait sa preuve partie.
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => __('guest.flash.proof_too_late')]);

        $this->assertSame(
            RegistrationStatus::Expired,
            $tenant->asCurrent(fn () => $registration->fresh())->status,
        );
    }

    public function test_un_jeton_de_reprise_d_une_autre_inscription_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());

        $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, '/register/'.str_repeat('a', 64).'/proof'),
            $this->validPayload($account),
        )->assertNotFound();
    }

    public function test_le_depot_de_preuve_est_limite_en_debit(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $account = $tenant->asCurrent(fn () => PaymentAccount::first());
        ['resume' => $resume] = $this->heldRegistration($tenant, $event);

        $url = $this->urlFor($tenant->subdomain, $event->public_token, "/register/{$resume}/proof");

        for ($i = 0; $i < 5; $i++) {
            $this->post($url, $this->validPayload($account));
        }

        $this->post($url, $this->validPayload($account))->assertStatus(429);
    }
}
