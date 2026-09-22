<?php

namespace Tests\Feature\Tenants;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneExpiredTenantInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_invitations_are_deleted_by_the_scheduled_cleanup(): void
    {
        $this->travelTo(now()->startOfDay());

        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->joinAsOwner($tenant, $owner);

        $expiredInvitation = TenantInvitation::factory()->expired()->create([
            'tenant_id' => $tenant->id,
            'invited_by' => $owner->id,
        ]);

        $unexpiredInvitation = TenantInvitation::factory()->expiresIn(1)->create([
            'tenant_id' => $tenant->id,
            'invited_by' => $owner->id,
        ]);

        $invitationWithoutExpiration = TenantInvitation::factory()->create([
            'tenant_id' => $tenant->id,
            'invited_by' => $owner->id,
        ]);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertDatabaseMissing('tenant_invitations', [
            'id' => $expiredInvitation->id,
        ]);

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $unexpiredInvitation->id,
        ]);

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitationWithoutExpiration->id,
        ]);
    }
}
