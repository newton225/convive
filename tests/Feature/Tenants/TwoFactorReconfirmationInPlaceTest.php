<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PaymentChannel;
use App\Enums\TenantPermission;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Le code a deux facteurs des comptes de versement (SECURITY.md C1) se redemande sur la page meme,
 * sans la quitter : renvoyer vers un autre ecran faisait perdre tout ce qui avait ete saisi
 * (constate le 2026-10-04).
 */
class TwoFactorReconfirmationInPlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function tenantOwnedBy(User $user): Tenant
    {
        return app(CreateTenant::class)->handle($user, 'Association Convive');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'label' => 'Wave principal',
            'channel' => PaymentChannel::Wave->value,
            'account_number' => '+225 07 00 00 00 01',
            'holder_name' => 'Association Convive',
            'is_active' => true,
        ];
    }

    private function submitFromPage(Tenant $tenant): TestResponse
    {
        return $this->from(route('tenants.payment-accounts.index', $tenant))
            ->withHeader('X-Inertia', 'true')
            ->post(route('tenants.payment-accounts.store', $tenant), $this->payload());
    }

    public function test_sans_code_recent_la_page_garde_la_saisie_et_demande_le_code(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner);

        $this->submitFromPage($tenant)
            ->assertRedirect(route('tenants.payment-accounts.index', $tenant))
            ->assertSessionHasErrors('two_factor_reconfirm');

        $this->assertSame(0, $tenant->asCurrent(fn () => PaymentAccount::count()));
    }

    /**
     * Un membre dont le profil n'exige pas la double authentification, mais qui gere les comptes de
     * versement : un Proprietaire sans elle n'atteint meme pas la page (`EnsureTwoFactorForProfile`).
     */
    private function treasurerWithoutTwoFactor(Tenant $tenant): User
    {
        $member = User::factory()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::TenantPaymentAccounts]);

        return $member;
    }

    public function test_sans_double_authentification_la_page_le_dit_sans_rien_perdre(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $this->actingAs($this->treasurerWithoutTwoFactor($tenant));

        $this->submitFromPage($tenant)
            ->assertRedirect(route('tenants.payment-accounts.index', $tenant))
            ->assertSessionHasErrors('two_factor_setup');

        $this->assertSame(0, $tenant->asCurrent(fn () => PaymentAccount::count()));
    }

    public function test_la_page_sait_si_la_double_authentification_est_active(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $this->actingAs($this->treasurerWithoutTwoFactor($tenant))
            ->get(route('tenants.payment-accounts.index', $tenant))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('twoFactorEnabled', false));
    }

    public function test_le_code_saisi_sur_la_page_y_ramene_et_debloque_l_envoi(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $page = route('tenants.payment-accounts.index', $tenant);

        $this->actingAs($owner);

        $code = app(Google2FA::class)->getCurrentOtp(UserFactory::TwoFactorSecret);

        $this->from($page)
            ->withHeader('X-Inertia', 'true')
            ->post(route('two-factor.reconfirm.store'), ['code' => $code])
            ->assertRedirect($page);

        $this->submitFromPage($tenant)->assertSessionHasNoErrors();

        $this->assertSame(1, $tenant->asCurrent(fn () => PaymentAccount::count()));
    }
}
