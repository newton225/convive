<?php

namespace Tests\Feature\Claims;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ClaimCategory;
use App\Enums\ClaimStatus;
use App\Enums\LegalForm;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\GuestClaim;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Les reclamations des invites (decision du 2026-10-09) : l'invite ecrit depuis son dossier sans
 * voir les coordonnees de l'organisation, qui la lit, rappelle la personne et la marque traitee.
 */
class GuestClaimTest extends TestCase
{
    use RefreshDatabase;

    private function publishableTenant(User $owner, string $subdomain = 'convive-ci'): Tenant
    {
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

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

    private function publishedEvent(Tenant $tenant): Event
    {
        return $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create();
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return $event->fresh();
        });
    }

    private function claimUrl(Tenant $tenant, Event $event, Registration $registration): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $domain = $tenant->subdomain.'.'.config('convive.public_domain');

        return ($appUrl['scheme'] ?? 'http')."://{$domain}{$port}/e/{$event->public_token}/register/{$registration->id}/claim";
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Registration $registration, array $overrides = []): array
    {
        return array_merge([
            'category' => ClaimCategory::Payment->value,
            'message' => 'J ai paye mais mon dossier est toujours en attente.',
            'signature' => $registration->notificationToken(),
        ], $overrides);
    }

    public function test_un_invite_envoie_une_reclamation_et_l_organisation_est_prevenue(): void
    {
        Notification::fake();
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $this->post($this->claimUrl($tenant, $event, $registration), $this->payload($registration))
            ->assertRedirect();

        $claim = $tenant->asCurrent(fn () => GuestClaim::query()->firstOrFail());

        $this->assertSame($registration->id, $claim->registration_id);
        $this->assertSame(ClaimCategory::Payment, $claim->category);
        $this->assertSame(ClaimStatus::Open, $claim->status);
        Notification::assertSentTo($owner, TenantAlert::class);
    }

    public function test_une_signature_alteree_ne_depose_rien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $this->post($this->claimUrl($tenant, $event, $registration), $this->payload($registration, ['signature' => str_repeat('a', 64)]))
            ->assertNotFound();

        $this->assertSame(0, $tenant->asCurrent(fn () => GuestClaim::query()->count()));
    }

    public function test_le_dossier_d_un_autre_evenement_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $other = $tenant->asCurrent(fn () => Event::factory()->published()->create());
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $other->id]));

        $this->post($this->claimUrl($tenant, $event, $registration), $this->payload($registration))
            ->assertNotFound();
    }

    public function test_un_message_trop_court_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $this->post($this->claimUrl($tenant, $event, $registration), $this->payload($registration, ['message' => 'Aide']))
            ->assertSessionHasErrors('message');
    }

    public function test_un_sujet_hors_catalogue_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $this->post($this->claimUrl($tenant, $event, $registration), $this->payload($registration, ['category' => 'insulte']))
            ->assertSessionHasErrors('category');
    }

    public function test_un_dossier_ne_cumule_pas_plus_de_reclamations_ouvertes_que_le_plafond(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            GuestClaim::factory()->count(GuestClaim::MaxOpenPerRegistration)->create(['registration_id' => $registration->id]);

            return $registration;
        });

        $this->post($this->claimUrl($tenant, $event, $registration), $this->payload($registration))
            ->assertRedirect();

        $this->assertSame(
            GuestClaim::MaxOpenPerRegistration,
            $tenant->asCurrent(fn () => GuestClaim::query()->count()),
        );
    }

    public function test_une_reclamation_traitee_ne_compte_plus_dans_le_plafond(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            GuestClaim::factory()->resolved()->count(GuestClaim::MaxOpenPerRegistration)->create(['registration_id' => $registration->id]);

            return $registration;
        });

        $this->post($this->claimUrl($tenant, $event, $registration), $this->payload($registration))
            ->assertRedirect();

        $this->assertSame(1, $tenant->asCurrent(fn () => GuestClaim::query()->open()->count()));
    }

    public function test_le_depot_de_reclamations_est_limite_par_heure(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        foreach (range(1, 3) as $attempt) {
            $this->post($this->claimUrl($tenant, $event, $registration), $this->payload($registration, ['message' => "Essai numero {$attempt} sur mon dossier."]))
                ->assertRedirect();
        }

        $this->post($this->claimUrl($tenant, $event, $registration), $this->payload($registration))
            ->assertStatus(429);
    }

    public function test_la_page_du_dossier_porte_la_signature_et_le_droit_d_ecrire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));

        $this->get($registration->signedResumeUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('claim.registrationId', $registration->id)
                ->where('claim.signature', $registration->notificationToken())
                ->where('claim.canSubmit', true)
                ->has('claim.categories', count(ClaimCategory::cases())));
    }

    public function test_un_membre_avec_la_permission_voit_la_liste_des_reclamations_ouvertes(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            GuestClaim::factory()->create(['registration_id' => $registration->id]);
            GuestClaim::factory()->resolved()->create(['registration_id' => $registration->id]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.claims.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/claims')
                ->where('filters.status', 'open')
                ->has('rows', 1)
                ->where('rows.0.status', 'open'));

        $this->actingAs($owner)
            ->get(route('tenants.events.claims.index', [$tenant, $event, 'filter' => ['status' => 'all']]))
            ->assertInertia(fn ($page) => $page->has('rows', 2));
    }

    public function test_un_membre_sans_la_permission_ne_voit_pas_les_reclamations(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView, TenantPermission::RegistrationsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.claims.index', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_les_reclamations(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.claims.index', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_marquer_une_reclamation_traitee_la_ferme_et_se_journalise(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $claim = $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);

            return GuestClaim::factory()->create(['registration_id' => $registration->id]);
        });

        $this->actingAs($owner)
            ->post(route('tenants.events.claims.resolve', [$tenant, $event, $claim]))
            ->assertRedirect();

        $claim = $tenant->asCurrent(fn () => $claim->fresh());

        $this->assertSame(ClaimStatus::Resolved, $claim->status);
        $this->assertNotNull($claim->resolved_at);
        $this->assertSame($owner->id, $claim->resolved_by_user_id);
        $this->assertSame(1, $tenant->asCurrent(fn () => Activity::query()->where('description', 'registrations.claim_resolved')->count()));
    }

    public function test_marquer_deux_fois_ne_reecrit_rien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $claim = $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);

            return GuestClaim::factory()->create(['registration_id' => $registration->id]);
        });

        $this->actingAs($owner)->post(route('tenants.events.claims.resolve', [$tenant, $event, $claim]));
        $this->actingAs($owner)->post(route('tenants.events.claims.resolve', [$tenant, $event, $claim]));

        $this->assertSame(1, $tenant->asCurrent(fn () => Activity::query()->where('description', 'registrations.claim_resolved')->count()));
    }

    public function test_un_membre_sans_la_permission_ne_traite_pas_une_reclamation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $claim = $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);

            return GuestClaim::factory()->create(['registration_id' => $registration->id]);
        });
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView, TenantPermission::RegistrationsView]);

        $this->actingAs($member)
            ->post(route('tenants.events.claims.resolve', [$tenant, $event, $claim]))
            ->assertForbidden();

        $this->assertSame(ClaimStatus::Open, $tenant->asCurrent(fn () => $claim->fresh())->status);
    }

    public function test_une_reclamation_d_un_autre_evenement_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $other = $tenant->asCurrent(fn () => Event::factory()->published()->create());
        $claim = $tenant->asCurrent(function () use ($other) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $other->id]);

            return GuestClaim::factory()->create(['registration_id' => $registration->id]);
        });

        $this->actingAs($owner)
            ->post(route('tenants.events.claims.resolve', [$tenant, $event, $claim]))
            ->assertNotFound();
    }

    public function test_la_carte_de_l_evenement_compte_les_reclamations_ouvertes(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            GuestClaim::factory()->count(2)->create(['registration_id' => $registration->id]);
            GuestClaim::factory()->resolved()->create(['registration_id' => $registration->id]);
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.index', $tenant))
            ->assertInertia(fn ($page) => $page->where('events.0.openClaims', 2));
    }

    public function test_une_reclamation_disparait_avec_l_inscription(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            GuestClaim::factory()->create(['registration_id' => $registration->id]);
            $registration->delete();
        });

        $this->assertSame(0, $tenant->asCurrent(fn () => GuestClaim::query()->count()));
    }
}
