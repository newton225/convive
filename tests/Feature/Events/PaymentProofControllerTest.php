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
}
