<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProofControllerTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @return array{event: Event, proof: PaymentProof}
     */
    private function proofSubmitted(Tenant $tenant): array
    {
        return $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);

            return ['event' => $event, 'proof' => $proof];
        });
    }

    public function test_un_membre_avec_la_permission_voit_la_file(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->proofSubmitted($tenant);

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/proofs')
                ->has('rows', 1),
            );
    }

    public function test_la_file_signale_un_evenement_dont_la_date_est_passee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->proofSubmitted($tenant);

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertInertia(fn ($page) => $page->where('event.hasPassed', false));

        $tenant->asCurrent(fn () => $event->update(['starts_at' => now()->subDay()]));

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertInertia(fn ($page) => $page->where('event.hasPassed', true));
    }

    public function test_un_membre_sans_la_permission_de_lecture_ne_voit_pas_la_file(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->proofSubmitted($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_la_file(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->proofSubmitted($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertNotFound();
    }

    public function test_un_membre_avec_la_permission_valide_une_preuve(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ProofsView, TenantPermission::ProofsApprove]);

        $this->actingAs($member)
            ->post(route('tenants.events.proofs.approve', [$tenant, $event, $proof]))
            ->assertRedirect(route('tenants.events.proofs.index', [$tenant, $event]));

        $this->assertSame(
            RegistrationStatus::Confirmed,
            $tenant->asCurrent(fn () => $proof->fresh()->registration)->status,
        );
    }

    public function test_un_membre_sans_la_permission_d_approbation_ne_valide_rien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ProofsView]);

        $this->actingAs($member)
            ->post(route('tenants.events.proofs.approve', [$tenant, $event, $proof]))
            ->assertForbidden();

        $this->assertSame(
            RegistrationStatus::ProofSubmitted,
            $tenant->asCurrent(fn () => $proof->fresh()->registration)->status,
        );
    }

    public function test_un_membre_avec_la_permission_rejette_une_preuve(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ProofsView, TenantPermission::ProofsReject]);

        $this->actingAs($member)
            ->post(route('tenants.events.proofs.reject', [$tenant, $event, $proof]))
            ->assertRedirect(route('tenants.events.proofs.index', [$tenant, $event]));

        $this->assertSame(
            RegistrationStatus::ProofRejected,
            $tenant->asCurrent(fn () => $proof->fresh()->registration)->status,
        );
    }

    public function test_un_membre_sans_la_permission_de_rejet_ne_rejette_rien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event, 'proof' => $proof] = $this->proofSubmitted($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::ProofsView]);

        $this->actingAs($member)
            ->post(route('tenants.events.proofs.reject', [$tenant, $event, $proof]))
            ->assertForbidden();
    }

    public function test_une_preuve_d_un_autre_evenement_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['proof' => $proof] = $this->proofSubmitted($tenant);
        $otherEvent = $tenant->asCurrent(fn () => Event::factory()->open()->create());

        $this->actingAs($owner)
            ->post(route('tenants.events.proofs.approve', [$tenant, $otherEvent, $proof]))
            ->assertNotFound();
    }

    public function test_une_capture_deja_vue_liste_les_autres_preuves_qui_la_portent(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        ['event' => $event, 'earlier' => $earlier] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $pending = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            PaymentProof::factory()->withPerceptualHash('ffffffffffffffff')->create(['registration_id' => $pending->id]);

            // Meme capture a un bit pres, deja validee sur un autre evenement : elle doit figurer.
            $otherEvent = Event::factory()->open()->create(['name' => 'Gala precedent']);
            $confirmed = Registration::factory()->confirmed()->create(['event_id' => $otherEvent->id, 'name' => 'Awa Kone']);
            $earlier = PaymentProof::factory()->withPerceptualHash('fffffffffffffffe')->withReference('WAVE-OLD')
                ->create(['registration_id' => $confirmed->id]);

            // Capture sans rapport : elle ne doit pas figurer.
            $unrelated = Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            PaymentProof::factory()->withPerceptualHash('0000000000000000')->create(['registration_id' => $unrelated->id]);

            return ['event' => $event, 'earlier' => $earlier];
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.signals.duplicateImage', true)
                ->has('rows.0.duplicateImageMatches', 1)
                ->where('rows.0.duplicateImageMatches.0.proofId', $earlier->id)
                ->where('rows.0.duplicateImageMatches.0.name', 'Awa Kone')
                ->where('rows.0.duplicateImageMatches.0.eventName', 'Gala precedent')
                ->where('rows.0.duplicateImageMatches.0.reference', 'WAVE-OLD')
                ->where('rows.0.duplicateImageMatches.0.status', RegistrationStatus::Confirmed->value),
            );
    }

    public function test_une_capture_inedite_ne_liste_aucune_autre_preuve(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        ['event' => $event] = $this->proofSubmitted($tenant);

        $this->actingAs($owner)
            ->get(route('tenants.events.proofs.index', [$tenant, $event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.signals.duplicateImage', false)
                ->has('rows.0.duplicateImageMatches', 0),
            );
    }
}
