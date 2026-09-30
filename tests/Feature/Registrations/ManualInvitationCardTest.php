<?php

namespace Tests\Feature\Registrations;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\Registrations\InvitationCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * README 2.7, envoi manuel de la carte d'invitation depuis la base d'inscrits, quand l'envoi
 * automatique n'a pas fonctionne ou qu'un invite a perdu sa carte.
 */
class ManualInvitationCardTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user): Tenant
    {
        $tenant = app(CreateTenant::class)->handle($user, 'Association Convive');
        // Le lien de la carte se construit sur le sous-domaine de l'organisation.
        $tenant->update(['subdomain' => 'convive-ci']);

        return $tenant;
    }

    /**
     * Une inscription validee de trois personnes, billets emis.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{event: Event, registration: Registration}
     */
    private function confirmedGroup(Tenant $tenant, array $attributes = []): array
    {
        return $tenant->asCurrent(function () use ($attributes) {
            $event = Event::factory()->published()->create();
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $event->id,
                'name' => 'Aya Kouassi',
                'phone' => '+2250707000000',
                'party_size' => 3,
                ...$attributes,
            ]);
            $unit = Unit::query()->value('id');
            $registration->companions()->create(['name' => 'Kofi Kouassi', 'unit_id' => $unit, 'position' => 0]);
            $registration->companions()->create(['name' => 'Marie Kouassi', 'unit_id' => $unit, 'position' => 1]);
            app(IssueTicket::class)->handle($registration);

            return ['event' => $event, 'registration' => $registration];
        });
    }

    public function test_un_membre_avec_la_permission_renvoie_une_carte_deja_envoyee(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'registration' => $registration] = $this->confirmedGroup($tenant, ['card_sent_at' => now()->subDays(3)]);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView, TenantPermission::MessagesSend]);

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.card.send', [$tenant, $event, $registration]))
            ->assertRedirect(route('tenants.events.registrations.index', [$tenant, $event]));

        Notification::assertSentOnDemand(InvitationCard::class);
        $this->assertTrue($tenant->asCurrent(fn () => $registration->fresh())->card_sent_at->isToday());
    }

    public function test_un_membre_sans_la_permission_ne_renvoie_pas_la_carte(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'registration' => $registration] = $this->confirmedGroup($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::RegistrationsView]);

        $this->actingAs($member)
            ->post(route('tenants.events.registrations.card.send', [$tenant, $event, $registration]))
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_un_locataire_tiers_recoit_404_sur_l_envoi_de_la_carte(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'registration' => $registration] = $this->confirmedGroup($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->post(route('tenants.events.registrations.card.send', [$tenant, $event, $registration]))
            ->assertNotFound();
    }

    public function test_une_inscription_non_validee_n_a_pas_de_carte_a_envoyer(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->published()->create());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]));

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.card.send', [$tenant, $event, $registration]))
            ->assertSessionHasErrors('card');

        Notification::assertNothingSent();
    }

    public function test_l_envoi_manuel_est_journalise_avec_son_auteur(): void
    {
        Notification::fake();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'registration' => $registration] = $this->confirmedGroup($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.card.send', [$tenant, $event, $registration]));

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'registrations.card_sent')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame($registration->id, $activity->subject_id);
    }

    public function test_la_carte_porte_le_lien_du_billet_de_chaque_accompagnateur(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['registration' => $registration] = $this->confirmedGroup($tenant);

        $message = $tenant->asCurrent(function () use ($registration) {
            $link = (string) $registration->signedResumeUrl();

            return (new InvitationCard($registration->fresh(), $link))->toWhatsApp(new AnonymousNotifiable);
        });

        $companionTickets = $tenant->asCurrent(fn () => Ticket::where('registration_id', $registration->id)
            ->where('holder_position', '>', Ticket::GuestPosition)
            ->get());

        $this->assertCount(2, $companionTickets);

        foreach ($companionTickets as $ticket) {
            $this->assertStringContainsString((string) $ticket->holder_name, $message);
            $this->assertStringContainsString((string) $tenant->asCurrent(fn () => $ticket->shareUrl()), $message);
        }
    }

    public function test_le_partage_du_billet_d_un_accompagnateur_est_journalise(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'registration' => $registration] = $this->confirmedGroup($tenant);
        $ticket = $tenant->asCurrent(fn () => Ticket::where('registration_id', $registration->id)->where('holder_position', 1)->firstOrFail());

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.card.shared', [$tenant, $event, $registration]), [
                'ticket' => $ticket->id,
                'via' => 'whatsapp',
            ])
            ->assertRedirect();

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'registrations.card_shared')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame($ticket->id, $activity->properties['ticket_id']);
        $this->assertSame('Kofi Kouassi', $activity->properties['holder']);
        $this->assertSame('whatsapp', $activity->properties['via']);
    }

    public function test_le_billet_d_une_autre_inscription_ne_se_partage_pas_depuis_celle_ci(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'registration' => $registration] = $this->confirmedGroup($tenant);
        ['registration' => $other] = $this->confirmedGroup($tenant, ['phone' => '+2250505000000']);
        $foreignTicket = $tenant->asCurrent(fn () => Ticket::where('registration_id', $other->id)->firstOrFail());

        $this->actingAs($owner)
            ->post(route('tenants.events.registrations.card.shared', [$tenant, $event, $registration]), [
                'ticket' => $foreignTicket->id,
                'via' => 'copy',
            ])
            ->assertSessionHasErrors('ticket');
    }

    public function test_les_liens_de_la_carte_ne_sont_transmis_qu_avec_la_permission(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->confirmedGroup($tenant);

        $reader = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $reader, [TenantPermission::RegistrationsView]);

        $this->actingAs($reader)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.card', null));

        $this->actingAs($owner)
            ->get(route('tenants.events.registrations.index', [$tenant, $event]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows.0.card.people', 3)
                ->where('rows.0.card.people.0.isHolder', true)
                ->where('rows.0.card.people.1.name', 'Kofi Kouassi'));
    }
}
