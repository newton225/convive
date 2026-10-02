<?php

namespace Tests\Feature\Console;

use App\Enums\ConsoleProfile;
use App\Models\ConsoleOperator;
use App\Models\MessageLog;
use App\Models\User;
use App\Notifications\Console\TwoFactorResetByEditor;
use App\Notifications\Registrations\PhoneVerificationCode;
use App\Support\Console\MessageJournal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le releve des envois (README section 3) : chaque courriel ou message WhatsApp parti laisse son
 * canal, son type et un destinataire masque, jamais son contenu ; un canal qui n'envoie pas
 * reellement est signale.
 */
class MessageJournalTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
    }

    public function test_un_courriel_parti_est_releve_avec_un_destinataire_masque(): void
    {
        $user = User::factory()->create(['email' => 'aya.kouassi@example.test']);

        $user->notify(new TwoFactorResetByEditor);

        $message = MessageLog::sole();

        $this->assertSame('mail', $message->channel);
        $this->assertSame('TwoFactorResetByEditor', $message->type);
        $this->assertSame('a***@example.test', $message->recipient);
    }

    public function test_un_message_whatsapp_est_releve_et_signale_comme_simule(): void
    {
        Notification::route('whatsapp', '+2250707123456')->notify(new class extends BaseNotification
        {
            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return ['whatsapp'];
            }

            public function toWhatsApp(object $notifiable): string
            {
                return 'Bonjour, voici votre carte.';
            }
        });

        $message = MessageLog::sole();

        $this->assertSame('whatsapp', $message->channel);
        $this->assertSame('+225********56', $message->recipient);
        // Le palliatif n'envoie rien : le releve le dit.
        $this->assertTrue($message->simulated);
        $this->assertTrue(MessageJournal::isSimulated('whatsapp'));
    }

    public function test_un_envoi_sans_destinataire_n_est_pas_compte_comme_parti(): void
    {
        Notification::route('mail', null)->notify(new class extends BaseNotification
        {
            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)->line('Sans adresse.');
            }
        });

        $this->assertSame(0, MessageLog::count());
    }

    public function test_le_masque_ne_laisse_pas_identifier_un_numero_ou_une_adresse(): void
    {
        $this->assertSame('a***@example.test', MessageJournal::mask('aya.kouassi@example.test'));
        $this->assertSame('+225********56', MessageJournal::mask('+2250707123456'));
        $this->assertSame('****', MessageJournal::mask('1234'));
    }

    public function test_les_envois_de_plus_de_trente_jours_sont_purges(): void
    {
        MessageLog::create(['channel' => 'mail', 'type' => 'InvitationCard', 'recipient' => 'a***@x.test', 'created_at' => now()->subDays(31)]);
        MessageLog::create(['channel' => 'mail', 'type' => 'InvitationCard', 'recipient' => 'a***@x.test', 'created_at' => now()->subDays(29)]);

        $this->assertSame(1, MessageJournal::purge());
        $this->assertSame(1, MessageLog::count());
    }

    public function test_l_ecran_compte_les_envois_par_canal_et_par_type(): void
    {
        MessageLog::create(['channel' => 'whatsapp', 'type' => class_basename(PhoneVerificationCode::class), 'recipient' => '+225********56', 'simulated' => true, 'created_at' => now()]);
        MessageLog::create(['channel' => 'mail', 'type' => 'InvitationCard', 'recipient' => 'a***@x.test', 'created_at' => now()->subDays(3)]);

        $this->actingAs($this->founder)
            ->get(route('console.messages'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('console/messages')
                ->has('messages', 2)
                ->where('messages.0.type', __('console.messages.types.PhoneVerificationCode'))
                ->where('channels', fn ($channels) => collect($channels)->firstWhere('channel', 'whatsapp')['lastDay'] === 1
                    && collect($channels)->firstWhere('channel', 'mail')['lastDay'] === 0
                    && collect($channels)->firstWhere('channel', 'mail')['lastWeek'] === 1),
            );
    }

    public function test_le_support_lit_les_envois_un_compte_ordinaire_non(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)->get(route('console.messages'))->assertOk();

        ConsoleOperator::create(['email' => 'compta@convive.test', 'profile' => ConsoleProfile::Accounting]);
        $accounting = User::factory()->withTwoFactor()->create(['email' => 'compta@convive.test']);

        $this->actingAs($accounting)->get(route('console.messages'))->assertForbidden();

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)->get(route('console.messages'))->assertNotFound();
    }
}
