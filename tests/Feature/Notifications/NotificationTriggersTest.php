<?php

namespace Tests\Feature\Notifications;

use App\Actions\Events\SaveEvent;
use App\Actions\Notifications\NotifyEventMilestones;
use App\Actions\Notifications\SendAlert;
use App\Actions\PaymentProofs\RejectPaymentProof;
use App\Actions\PaymentProofs\SubmitPaymentProof;
use App\Actions\Registrations\CancelRegistration;
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
            'tables' => [1, 2],
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
                ['payment_account_id' => $account->id, 'channel' => 'wave', 'reference' => 'WV0001', 'guest_note' => null],
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
            $data = ['payment_account_id' => $account->id, 'channel' => 'wave', 'reference' => 'WV0001', 'guest_note' => null];

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

    public function test_reserver_sans_epuiser_les_places_previent_d_une_nouvelle_inscription_sans_alerte_de_places(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->create(['event_id' => $this->event->id, 'party_size' => 1, 'amount_due' => 15000]);

            app(HoldRegistration::class)->handle($this->event, $registration);
        });

        $this->assertAlerted($this->owner, NotificationType::RegistrationReceived);
        Notification::assertNotSentTo($this->owner, TenantAlert::class, fn (TenantAlert $alert) => in_array($alert->type, [NotificationType::SeatsExhausted, NotificationType::SeatsLow], true));
    }

    public function test_la_relance_d_une_reservation_expiree_n_est_pas_une_nouvelle_inscription(): void
    {
        Notification::fake();

        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->expired()->create(['event_id' => $this->event->id, 'party_size' => 1, 'amount_due' => 15000]);

            app(HoldRegistration::class)->handle($this->event, $registration);
        });

        Notification::assertNotSentTo($this->owner, TenantAlert::class, fn (TenantAlert $alert) => $alert->type === NotificationType::RegistrationReceived);
    }

    public function test_les_inscriptions_non_lues_d_un_evenement_se_regroupent_en_une_alerte(): void
    {
        $this->tenant->asCurrent(function () {
            foreach (range(1, 3) as $attempt) {
                app(SendAlert::class)->toTenantMembersGrouped(
                    NotificationType::RegistrationReceived,
                    ['event' => $this->event->name, 'event_id' => $this->event->id],
                    '/',
                );
            }
        });

        $alerts = $this->owner->unreadNotifications()->where('data->type', NotificationType::RegistrationReceived->value)->get();

        $this->assertCount(1, $alerts);
        $this->assertSame(3, $alerts->first()->data['params']['count']);
    }

    public function test_une_alerte_lue_n_est_plus_regroupee(): void
    {
        $send = fn () => $this->tenant->asCurrent(fn () => app(SendAlert::class)->toTenantMembersGrouped(
            NotificationType::RegistrationReceived,
            ['event' => $this->event->name, 'event_id' => $this->event->id],
            '/',
        ));

        $send();
        $this->owner->unreadNotifications->markAsRead();
        $send();

        $this->assertCount(1, $this->owner->unreadNotifications()->get());
        $this->assertCount(2, $this->owner->notifications()->where('data->type', NotificationType::RegistrationReceived->value)->get());
    }

    public function test_publier_un_evenement_previent_les_autres_membres_mais_pas_celui_qui_publie(): void
    {
        Notification::fake();

        $publisher = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $publisher, [TenantPermission::EventsView]);

        $this->actingAs($publisher);
        $this->tenant->asCurrent(fn () => app(SaveEvent::class)->publish($this->event));

        $this->assertAlerted($this->owner, NotificationType::EventPublished);
        Notification::assertNotSentTo($publisher, TenantAlert::class);
    }

    public function test_une_annulation_previent_les_autres_membres_mais_pas_celui_qui_annule(): void
    {
        Notification::fake();

        $canceller = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $canceller, [TenantPermission::RegistrationsView]);

        $this->tenant->asCurrent(function () use ($canceller) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $this->event->id]);

            app(CancelRegistration::class)->handle($registration, 'Doublon', $canceller);
        });

        $this->assertAlerted($this->owner, NotificationType::RegistrationCancelled);
        Notification::assertNotSentTo($canceller, TenantAlert::class);
    }

    public function test_la_fin_d_un_evenement_previent_l_equipe_une_seule_fois(): void
    {
        Notification::fake();

        $ended = $this->tenant->asCurrent(fn () => Event::factory()->published()->create([
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'registration_deadline' => now()->subDays(5),
        ]));

        $this->tenant->asCurrent(function () use ($ended) {
            app(NotifyEventMilestones::class)->handle($ended);
            app(NotifyEventMilestones::class)->handle($ended->fresh());
        });

        Notification::assertSentToTimes($this->owner, TenantAlert::class, 1);
        $this->assertAlerted($this->owner, NotificationType::EventEnded);
    }

    public function test_la_date_limite_d_inscription_atteinte_previent_l_equipe_avant_la_fin(): void
    {
        Notification::fake();

        $open = $this->tenant->asCurrent(fn () => Event::factory()->published()->create([
            'starts_at' => now()->addWeek(),
            'ends_at' => null,
            'registration_deadline' => now()->subHour(),
        ]));

        $this->tenant->asCurrent(fn () => app(NotifyEventMilestones::class)->handle($open));

        $this->assertAlerted($this->owner, NotificationType::RegistrationsClosed);
        $this->assertNotNull($this->tenant->asCurrent(fn () => $open->fresh())->registrations_closed_alerted_at);
        $this->assertNull($this->tenant->asCurrent(fn () => $open->fresh())->ended_alerted_at);
    }

    public function test_un_evenement_cloture_a_la_main_ne_declenche_pas_les_alertes_de_fin(): void
    {
        Notification::fake();

        $closed = $this->tenant->asCurrent(fn () => Event::factory()->published()->closed()->create([
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
        ]));

        $this->tenant->asCurrent(fn () => app(NotifyEventMilestones::class)->handle($closed));

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
