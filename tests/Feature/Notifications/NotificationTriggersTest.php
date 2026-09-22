<?php

namespace Tests\Feature\Notifications;

use App\Actions\PaymentProofs\RejectPaymentProof;
use App\Actions\PaymentProofs\SubmitPaymentProof;
use App\Actions\Registrations\ExpireHolds;
use App\Actions\Registrations\HoldRegistration;
use App\Actions\Registrations\PurgeRegistrations;
use App\Actions\Scan\ScanTicket;
use App\Actions\Tenants\CreateTenant;
use App\Enums\NotificationType;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Profile;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Les sept evenements notifiables de README section 5, etape 10 de « Ordre de construction » :
 * chaque action metier qui les produit previent l'equipe concernee.
 */
class NotificationTriggersTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaRoot;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        // Meme isolation que `Tests\Feature\PaymentProofs\SubmitPaymentProofTest`.
        $this->mediaRoot = storage_path('framework/testing/tenant-media-'.Str::random(8));
        config(['filesystems.disks.tenant_media.root' => $this->mediaRoot]);
        Storage::forgetDisk('tenant_media');

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create([
            'name' => 'Gala',
            'table_count' => 1,
            'seats_per_table' => 2,
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaRoot);

        parent::tearDown();
    }

    private function assertAlerted(User $user, NotificationType $type): void
    {
        Notification::assertSentTo($user, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === $type);
    }

    public function test_une_preuve_deposee_previent_l_equipe(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->held()->create(['event_id' => $this->event->id, 'name' => 'Aya Kouassi']);
            $account = PaymentAccount::factory()->create();

            app(SubmitPaymentProof::class)->handle(
                $registration,
                ['payment_account_id' => $account->id, 'channel' => 'wave', 'reference' => 'WV0001', 'amount_declared' => 15000],
                UploadedFile::fake()->image('recu.png', 120, 120),
                (string) Str::uuid(),
            );
        });

        $this->assertAlerted($this->owner, NotificationType::ProofReceived);
    }

    public function test_un_rejeu_de_la_meme_preuve_ne_previent_pas_deux_fois(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->held()->create(['event_id' => $this->event->id]);
            $account = PaymentAccount::factory()->create();
            $key = (string) Str::uuid();
            $data = ['payment_account_id' => $account->id, 'channel' => 'wave', 'reference' => 'WV0001', 'amount_declared' => 15000];

            app(SubmitPaymentProof::class)->handle($registration, $data, UploadedFile::fake()->image('recu.png', 120, 120), $key);
            app(SubmitPaymentProof::class)->handle($registration, $data, UploadedFile::fake()->image('recu.png', 120, 120), $key);
        });

        Notification::assertSentToTimes($this->owner, TenantAlert::class, 1);
    }

    public function test_un_rejet_previent_les_autres_membres_mais_pas_celui_qui_a_rejete(): void
    {
        Notification::fake();

        $treasurer = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $treasurer, [TenantPermission::ProofsView, TenantPermission::ProofsReject]);

        $this->tenant->asCurrent(function () use ($treasurer) {
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $this->event->id]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);

            app(RejectPaymentProof::class)->handle($proof, $treasurer);
        });

        $this->assertAlerted($this->owner, NotificationType::ProofRejected);
        Notification::assertNotSentTo($treasurer, TenantAlert::class);
    }

    public function test_des_reservations_expirees_previennent_l_equipe_une_seule_fois_par_evenement(): void
    {
        Notification::fake();

        $expired = $this->tenant->asCurrent(function () {
            Registration::factory()->count(2)->create([
                'event_id' => $this->event->id,
                'status' => 'held',
                'held_until' => now()->subMinute(),
            ]);

            return app(ExpireHolds::class)->handle($this->event);
        });

        $this->assertSame(2, $expired);
        Notification::assertSentToTimes($this->owner, TenantAlert::class, 1);
        Notification::assertSentTo($this->owner, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::HoldsExpired && $alert->params['count'] === 2);
    }

    public function test_sans_reservation_expiree_rien_n_est_envoye(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(function () {
            Registration::factory()->held()->create(['event_id' => $this->event->id]);

            $this->assertSame(0, app(ExpireHolds::class)->handle($this->event));
        });

        Notification::assertNothingSent();
    }

    public function test_reserver_la_derniere_place_previent_que_les_places_sont_epuisees(): void
    {
        Notification::fake();

        $held = $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->create(['event_id' => $this->event->id, 'party_size' => 2]);

            return app(HoldRegistration::class)->handle($this->event, $registration);
        });

        $this->assertTrue($held);
        $this->assertAlerted($this->owner, NotificationType::SeatsExhausted);
    }

    public function test_reserver_sans_epuiser_les_places_ne_previent_pas(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->create(['event_id' => $this->event->id, 'party_size' => 1]);

            app(HoldRegistration::class)->handle($this->event, $registration);
        });

        Notification::assertNothingSent();
    }

    public function test_une_purge_previent_l_equipe_avec_le_nombre_de_dossiers(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(function () {
            Registration::factory()->expired()->count(3)->create(['event_id' => $this->event->id]);

            app(PurgeRegistrations::class)->handle($this->event);
        });

        Notification::assertSentTo($this->owner, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::RegistrationsPurged && $alert->params['count'] === 3);
    }

    public function test_une_purge_qui_ne_supprime_rien_ne_previent_personne(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(fn () => app(PurgeRegistrations::class)->handle($this->event));

        Notification::assertNothingSent();
    }

    public function test_un_billet_refuse_previent_les_membres_du_journal_sauf_l_agent(): void
    {
        Notification::fake();

        $agent = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $agent, [TenantPermission::ScanPerform, TenantPermission::ScanLogView]);

        $this->tenant->asCurrent(fn () => app(ScanTicket::class)->handle($this->event, 'jeton-invalide', $agent));

        $this->assertAlerted($this->owner, NotificationType::TicketRefused);
        Notification::assertNotSentTo($agent, TenantAlert::class);
    }

    public function test_une_invitation_previent_l_invite_qui_a_deja_un_compte(): void
    {
        Notification::fake();

        $invitee = User::factory()->create(['email' => 'invite@example.com']);
        $profileId = $this->tenant->run(fn () => Profile::where('name', 'Lecture')->value('id'));

        $this->actingAs($this->owner)
            ->post(route('tenants.invitations.store', $this->tenant), ['email' => 'invite@example.com', 'profile_id' => $profileId])
            ->assertRedirect();

        Notification::assertSentTo($invitee, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::TeamInvitationPending
            && $alert->params['tenant'] === 'Association Convive'
            && $alert->params['profile'] === 'Lecture');
    }

    public function test_une_invitation_a_une_adresse_sans_compte_n_envoie_que_le_courriel(): void
    {
        Notification::fake();

        $profileId = $this->tenant->run(fn () => Profile::where('name', 'Lecture')->value('id'));

        $this->actingAs($this->owner)
            ->post(route('tenants.invitations.store', $this->tenant), ['email' => 'inconnu@example.com', 'profile_id' => $profileId])
            ->assertRedirect();

        Notification::assertSentTimes(TenantAlert::class, 0);
    }
}
