<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * L'acces du support (README ecran 25 et section 3, « Console d'exploitation »), phase interface :
 * seul un Proprietaire de l'organisation ouvre un acces a l'equipe Convive. Un autre membre, meme
 * detenteur de toutes les autres permissions, ne le peut pas ; un locataire tiers recoit 404.
 */
class SupportAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    public function test_un_proprietaire_ouvre_l_ecran_de_l_acces_du_support(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenants/support-access')
                ->where('isSample', true)
                ->has('activeAccess')
                ->has('pastAccesses')
                ->has('operators')
                ->has('durations'),
            );
    }

    public function test_un_membre_qui_n_est_pas_proprietaire_est_refuse(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, TenantPermission::cases());

        $this->actingAs($member)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($stranger, 'Autre organisation');

        $this->actingAs($stranger)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertNotFound();
    }
}
