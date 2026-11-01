<?php

namespace Tests\Feature\Seating;

use App\Actions\Seating\MoveRegistrationToTable;
use App\Actions\Tenants\CreateTenant;
use App\Jobs\NotifyTableChange;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Registrations\TableChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Le message de changement de table (decision du 2026-10-31) : envoye une fois, un peu apres le dernier
 * deplacement, avec la table finale, et seulement a un invite dont la carte est deja partie.
 *
 * Chaque scenario se joue dans une seule entree dans l'organisation (`inTenant`) : le cache de test est en
 * memoire, et `stancl/tenancy` en cree un neuf a chaque entree, ce qui ferait perdre le message en attente entre
 * deux appels. En production le cache est partage (Redis) et ne s'efface pas.
 */
class TableChangeNoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    /** @var array<int, SeatingTable> */
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        // Sans sous-domaine et sans evenement publie, le lien du billet ne peut pas etre construit (et rien ne part).
        $this->tenant->update(['subdomain' => 'convive-ci']);
        $this->tenant = $this->tenant->fresh();

        $this->tenant->asCurrent(function () {
            $this->event = Event::factory()->published()->create(['tables' => null]);

            foreach ([1, 2, 3] as $number) {
                $this->tables[$number] = SeatingTable::factory()->create([
                    'event_id' => $this->event->id,
                    'number' => $number,
                    'capacity' => 6,
                ]);
            }
        });
    }

    private function inTenant(callable $scenario): void
    {
        $this->tenant->asCurrent($scenario);
    }

    /**
     * Une inscription confirmee, assise a la table 1 ; sa carte est partie sauf indication contraire.
     * A creer dans `inTenant`.
     */
    private function seatedGuest(bool $cardSent = true): Registration
    {
        $registration = Registration::factory()->confirmed()->create([
            'event_id' => $this->event->id,
            'party_size' => 1,
            'email' => 'aya@example.com',
            'card_sent_at' => $cardSent ? now() : null,
        ]);

        RegistrationTableAssignment::create([
            'registration_id' => $registration->id,
            'seating_table_id' => $this->tables[1]->id,
        ]);

        return $registration;
    }

    private function moveTo(Registration $registration, ?int $number): void
    {
        app(MoveRegistrationToTable::class)->handle(
            $registration,
            $number === null ? null : $this->tables[$number],
            $this->owner,
        );
    }

    private function runNotice(Registration $registration): void
    {
        (new NotifyTableChange($registration))->handle();
    }

    public function test_un_deplacement_programme_un_seul_envoi_meme_apres_plusieurs_gestes(): void
    {
        Queue::fake();

        $this->inTenant(function () {
            $registration = $this->seatedGuest();

            $this->moveTo($registration, 2);
            $this->moveTo($registration, 3);
            $this->moveTo($registration, 2);
        });

        Queue::assertPushed(NotifyTableChange::class, 1);
    }

    public function test_l_invite_est_prevenu_une_fois_avec_la_table_finale(): void
    {
        Queue::fake();
        Notification::fake();

        $this->inTenant(function () {
            $registration = $this->seatedGuest();

            $this->moveTo($registration, 2);
            $this->moveTo($registration, 3);
            $this->runNotice($registration);
        });

        Notification::assertSentOnDemandTimes(TableChanged::class, 1);
        Notification::assertSentOnDemand(TableChanged::class, fn (TableChanged $notification) => $notification->tableNumber === 3);
    }

    public function test_aucun_message_si_l_invite_revient_a_la_table_qu_il_connaissait(): void
    {
        Queue::fake();
        Notification::fake();

        $this->inTenant(function () {
            $registration = $this->seatedGuest();

            $this->moveTo($registration, 2);
            $this->moveTo($registration, 1);

            // Le message etait bien en attente : c'est son contenu qui n'a rien a dire.
            $this->assertTrue(Cache::has(NotifyTableChange::cacheKey($registration->id)));

            $this->runNotice($registration);
        });

        Notification::assertNothingSent();
    }

    public function test_aucun_message_si_la_carte_n_est_pas_encore_partie(): void
    {
        Queue::fake();
        Notification::fake();

        $this->inTenant(function () {
            $registration = $this->seatedGuest(cardSent: false);

            $this->moveTo($registration, 2);
            $this->assertFalse(Cache::has(NotifyTableChange::cacheKey($registration->id)));
        });

        Queue::assertNothingPushed();
        Notification::assertNothingSent();
    }

    public function test_aucun_message_quand_l_invite_est_retire_de_sa_table(): void
    {
        Queue::fake();
        Notification::fake();

        $this->inTenant(function () {
            $registration = $this->seatedGuest();

            $this->moveTo($registration, null);
            $this->assertTrue(Cache::has(NotifyTableChange::cacheKey($registration->id)));

            $this->runNotice($registration);
        });

        Notification::assertNothingSent();
    }

    public function test_un_invite_sans_table_est_prevenu_quand_on_lui_en_donne_une(): void
    {
        Queue::fake();
        Notification::fake();

        $this->inTenant(function () {
            $registration = $this->seatedGuest();

            $this->moveTo($registration, null);
            $this->moveTo($registration, 3);
            $this->runNotice($registration);
        });

        Notification::assertSentOnDemandTimes(TableChanged::class, 1);
        Notification::assertSentOnDemand(TableChanged::class, fn (TableChanged $notification) => $notification->tableNumber === 3);
    }

    public function test_aucun_message_si_l_evenement_est_termine(): void
    {
        Queue::fake();
        Notification::fake();

        $this->inTenant(function () {
            $registration = $this->seatedGuest();

            $this->moveTo($registration, 2);
            $this->event->forceFill(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)])->save();

            $this->runNotice($registration);
        });

        Notification::assertNothingSent();
    }

    public function test_aucun_message_si_l_inscription_n_est_plus_confirmee(): void
    {
        Queue::fake();
        Notification::fake();

        $this->inTenant(function () {
            $registration = $this->seatedGuest();

            $this->moveTo($registration, 2);
            $registration->forceFill(['status' => 'cancelled'])->save();

            $this->runNotice($registration);
        });

        Notification::assertNothingSent();
    }

    public function test_le_message_decrit_la_table_et_le_lien_du_billet(): void
    {
        $text = null;

        $this->inTenant(function () use (&$text) {
            $registration = $this->seatedGuest()->load('event');

            $text = (new TableChanged($registration, 4, 'https://example.test/billet'))->toWhatsApp(new User);
        });

        $this->assertStringContainsString('table 4', $text);
        $this->assertStringContainsString('https://example.test/billet', $text);
        $this->assertNotSame('guest.whatsapp.table_changed', $text);
    }

    public function test_le_message_en_attente_est_oublie_apres_l_envoi(): void
    {
        Queue::fake();
        Notification::fake();

        $this->inTenant(function () {
            $registration = $this->seatedGuest();

            $this->moveTo($registration, 2);
            $this->assertTrue(Cache::has(NotifyTableChange::cacheKey($registration->id)));

            $this->runNotice($registration);

            // Un second deplacement apres l'envoi programme un nouveau message.
            $this->assertFalse(Cache::has(NotifyTableChange::cacheKey($registration->id)));
        });
    }
}
