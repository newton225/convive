<?php

namespace Tests\Feature\Registrations;

use App\Actions\Claims\SubmitGuestClaim;
use App\Actions\Registrations\ExpireHolds;
use App\Actions\Registrations\HoldRegistration;
use App\Actions\Registrations\PruneRegistrationEventIps;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Waitlist\JoinWaitlist;
use App\Enums\ClaimCategory;
use App\Enums\RegistrationEventActor;
use App\Enums\RegistrationEventType;
use App\Enums\RegistrationStatus;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationEvent;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\RegistrationChain;
use App\Support\RegistrationJournal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'historique d'un dossier : les faits de l'invite et du systeme sont ecrits, rattaches au dossier,
 * sans nom ni telephone, et l'adresse IP ne reste que douze mois.
 */
class RegistrationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Event $event;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create(['tables' => [1, 2]]));
    }

    private function fact(Registration $registration, RegistrationEventType $type, array $attributes = []): RegistrationEvent
    {
        return RegistrationEvent::create([
            'event_id' => $this->event->id,
            'registration_id' => $registration->id,
            'type' => $type,
            'actor' => RegistrationEventActor::Guest,
            ...$attributes,
        ]);
    }

    public function test_une_reservation_ecrit_un_fait_de_l_invite_avec_son_adresse(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->create(['event_id' => $this->event->id, 'status' => RegistrationStatus::Draft]);

            $this->assertTrue(app(HoldRegistration::class)->handle($this->event, $registration));

            // Un evenement gratuit confirme aussitot : billets et table suivent, ce sont d'autres faits.
            $fact = RegistrationEvent::where('registration_id', $registration->id)
                ->where('type', RegistrationEventType::Held)
                ->sole();

            $this->assertSame(RegistrationEventActor::Guest, $fact->actor);
            $this->assertNotNull($fact->ip_address);
        });
    }

    public function test_l_expiration_ecrit_un_fait_du_systeme_sans_adresse(): void
    {
        $this->tenant->asCurrent(function () {
            $late = Registration::factory()->create(['event_id' => $this->event->id, 'status' => RegistrationStatus::Held, 'held_until' => now()->subMinute()]);
            $running = Registration::factory()->held()->create(['event_id' => $this->event->id]);

            app(ExpireHolds::class)->handle($this->event);

            $fact = RegistrationEvent::where('registration_id', $late->id)->sole();

            $this->assertSame(RegistrationEventType::HoldExpired, $fact->type);
            $this->assertSame(RegistrationEventActor::System, $fact->actor);
            $this->assertNull($fact->ip_address);
            $this->assertSame(0, RegistrationEvent::where('registration_id', $running->id)->count());
        });
    }

    public function test_la_liste_d_attente_ecrit_un_fait_sans_nom_ni_telephone(): void
    {
        $this->tenant->asCurrent(function () {
            $joined = app(JoinWaitlist::class)->handle($this->event, [
                'name' => 'Awa Kouame',
                'phone' => '+2250707070707',
                'unit_id' => Unit::factory()->create()->id,
                'price_category_id' => null,
                'companions' => [],
            ]);

            $fact = RegistrationEvent::where('waitlist_entry_id', $joined['entry']->id)->sole();
            $stored = json_encode($fact->toArray());

            $this->assertSame(RegistrationEventType::WaitlistJoined, $fact->type);
            $this->assertStringNotContainsString('Kouame', $stored);
            $this->assertStringNotContainsString('0707070707', $stored);
        });
    }

    public function test_une_reclamation_ecrit_un_fait_du_dossier(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $this->event->id]);

            app(SubmitGuestClaim::class)->handle($registration, ClaimCategory::cases()[0], 'Mon billet ne s\'affiche pas.');

            $this->assertSame(
                RegistrationEventType::ClaimSubmitted,
                RegistrationEvent::where('registration_id', $registration->id)->sole()->type,
            );
        });
    }

    public function test_l_adresse_ip_est_effacee_apres_douze_mois_et_le_fait_reste(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $this->event->id]);

            $old = $this->fact($registration, RegistrationEventType::Held, ['ip_address' => '203.0.113.7']);
            $old->forceFill(['created_at' => now()->subMonths(13)])->save();
            $recent = $this->fact($registration, RegistrationEventType::ProofSubmitted, ['ip_address' => '203.0.113.8']);

            $this->assertSame(1, app(PruneRegistrationEventIps::class)->handle());

            $this->assertNull($old->fresh()->ip_address);
            $this->assertSame('203.0.113.8', $recent->fresh()->ip_address);
        });
    }

    public function test_la_chaine_d_un_dossier_est_intacte_puis_se_rompt_si_une_ligne_est_modifiee_ou_retiree(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->held()->create(['event_id' => $this->event->id]);
            $other = Registration::factory()->held()->create(['event_id' => $this->event->id]);

            foreach ([RegistrationEventType::Held, RegistrationEventType::ProofSubmitted, RegistrationEventType::TicketsIssued] as $type) {
                RegistrationJournal::guest($type, $registration);
            }
            RegistrationJournal::guest(RegistrationEventType::Held, $other);

            $facts = RegistrationEvent::where('registration_id', $registration->id)->orderBy('id')->get();

            $this->assertCount(3, $facts);
            $this->assertNull($facts[0]->previous_hash);
            $this->assertSame($facts[0]->hash, $facts[1]->previous_hash);
            $this->assertNull(RegistrationChain::firstBrokenEvent());

            // L'effacement prevu de l'adresse IP ne casse pas la chaine.
            RegistrationEvent::query()->update(['ip_address' => null]);
            $this->assertNull(RegistrationChain::firstBrokenEvent());

            RegistrationEvent::whereKey($facts[1]->id)->update(['type' => RegistrationEventType::HoldExpired->value]);
            $this->assertSame($facts[1]->id, RegistrationChain::firstBrokenEvent());

            RegistrationEvent::whereKey($facts[1]->id)->update(['type' => RegistrationEventType::ProofSubmitted->value]);
            $this->assertNull(RegistrationChain::firstBrokenEvent());

            RegistrationEvent::whereKey($facts[1]->id)->delete();
            $this->assertSame($facts[2]->id, RegistrationChain::firstBrokenEvent());
        });
    }

    public function test_le_fait_disparait_avec_son_dossier(): void
    {
        $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->held()->create(['event_id' => $this->event->id]);
            $this->fact($registration, RegistrationEventType::Held);

            $registration->delete();

            $this->assertSame(0, RegistrationEvent::count());
        });
    }

    public function test_l_adresse_n_est_montree_qu_a_qui_peut_lire_le_journal_d_audit(): void
    {
        $registration = $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $this->event->id]);
            $this->fact($registration, RegistrationEventType::Held, ['ip_address' => '203.0.113.7']);

            return $registration;
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.history', [$this->tenant, $this->event, $registration]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/registration-history')
                ->where('showsIp', true)
                ->where('timeline.0.ip', '203.0.113.7'),
            );

        $reader = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $reader, [TenantPermission::RegistrationsView]);

        $this->actingAs($reader)
            ->get(route('tenants.events.registrations.history', [$this->tenant, $this->event, $registration]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showsIp', false)
                ->where('timeline.0.ip', null),
            );
    }

    public function test_un_membre_sans_la_permission_ne_lit_pas_l_historique(): void
    {
        $registration = $this->tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $this->event->id]));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.registrations.history', [$this->tenant, $this->event, $registration]))
            ->assertForbidden();
    }

    public function test_un_dossier_d_un_autre_evenement_recoit_404(): void
    {
        $registration = $this->tenant->asCurrent(function () {
            $other = Event::factory()->open()->create();

            return Registration::factory()->confirmed()->create(['event_id' => $other->id]);
        });

        $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.history', [$this->tenant, $this->event, $registration]))
            ->assertNotFound();
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        $registration = $this->tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $this->event->id]));

        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route('tenants.events.registrations.history', [$this->tenant, $this->event, $registration]))
            ->assertNotFound();
    }
}
