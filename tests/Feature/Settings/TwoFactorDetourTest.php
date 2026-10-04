<?php

namespace Tests\Feature\Settings;

use App\Actions\Tenants\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Envoye vers l'ecran de securite pour activer la double authentification, on y lit pourquoi et
 * quoi faire, puis on revient la ou on allait (constate le 2026-10-04 : arrive depuis les comptes
 * de versement, on ne savait pas quoi faire).
 */
class TwoFactorDetourTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user): Tenant
    {
        return app(CreateTenant::class)->handle($user, 'Association Convive');
    }

    public function test_venu_des_comptes_de_versement_l_ecran_dit_pourquoi_et_ou_revenir(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('security.edit', ['for' => 'payment-accounts', 'tenant' => $tenant->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('twoFactorDetour.reason', 'payment_accounts')
                ->where('twoFactorDetour.returnUrl', route('tenants.payment-accounts.index', $tenant, absolute: false)));
    }

    public function test_l_explication_reste_pendant_l_activation(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('security.edit', ['for' => 'payment-accounts', 'tenant' => $tenant->slug]));

        // Activer la double authentification recharge l'ecran sans les parametres d'origine.
        $this->get(route('security.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('twoFactorDetour.reason', 'payment_accounts'));
    }

    public function test_une_organisation_dont_on_n_est_pas_membre_ne_donne_aucun_retour(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('security.edit', ['for' => 'payment-accounts', 'tenant' => $tenant->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('twoFactorDetour', null));
    }

    public function test_un_profil_qui_exige_la_double_authentification_explique_le_detour(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('tenants.organisation.edit', $tenant))
            ->assertRedirect(route('security.edit'));

        $this->get(route('security.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('twoFactorDetour.reason', 'profile')
                ->where('twoFactorDetour.returnUrl', route('tenants.organisation.edit', $tenant, absolute: false)));
    }

    public function test_une_fois_activee_le_retour_est_propose_puis_oublie(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route('security.edit', ['for' => 'payment-accounts', 'tenant' => $tenant->slug]));

        $owner->forceFill(User::factory()->withTwoFactor()->make()->only([
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        ]))->save();

        $this->get(route('security.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('twoFactorDetour.reason', 'payment_accounts'));

        $this->get(route('security.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('twoFactorDetour', null));
    }
}
