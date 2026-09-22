<?php

namespace Tests\Feature\Billing;

use App\Actions\Tenants\CreateTenant;
use App\Contracts\SubscriptionBillingGateway;
use App\Enums\BillingCurrency;
use App\Enums\PlanCode;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Le franc CFA, l'euro et le dollar pour l'abonnement (decision du proprietaire, 2026-09-21) :
 * choix de la devise a l'ecran d'abonnement, etape 10.
 */
class BillingCurrencyTest extends TestCase
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

    private function expectCheckoutIn(BillingCurrency $currency): void
    {
        $this->mock(SubscriptionBillingGateway::class, function (MockInterface $gateway) use ($currency) {
            $gateway->shouldReceive('checkoutUrl')
                ->once()
                ->with(Mockery::type(Tenant::class), Mockery::type(Plan::class), $currency)
                ->andReturn('https://checkout.example/session');
        });
    }

    public function test_l_ecran_propose_les_devises_configurees_et_le_prix_de_chaque_plan_dans_chacune(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertInertia(fn ($page) => $page
                ->where('currencies', ['XOF', 'EUR', 'USD'])
                ->where('defaultCurrency', 'XOF')
                ->where('plans.1.code', 'association')
                ->where('plans.1.prices.XOF', 25000)
                ->where('plans.1.prices.EUR', 3800)
                ->where('plans.1.prices.USD', 4200)
                ->where('plans.2.prices.EUR', null),
            );
    }

    public function test_une_devise_retiree_de_la_configuration_n_est_plus_proposee(): void
    {
        config(['convive.billing.currencies' => ['EUR', 'USD']]);

        $this->actingAs($this->owner)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertInertia(fn ($page) => $page
                ->where('currencies', ['EUR', 'USD'])
                ->where('defaultCurrency', 'EUR')
                ->missing('plans.1.prices.XOF'),
            );
    }

    public function test_la_devise_deja_choisie_par_l_organisation_est_preselectionnee(): void
    {
        Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'currency' => 'USD']);

        $this->actingAs($this->owner)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertInertia(fn ($page) => $page->where('defaultCurrency', 'USD'));
    }

    public function test_le_paiement_part_dans_la_devise_choisie(): void
    {
        $this->expectCheckoutIn(BillingCurrency::Eur);

        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'association']), ['currency' => 'EUR'])
            ->assertRedirect('https://checkout.example/session');
    }

    public function test_sans_devise_le_paiement_part_dans_la_premiere_devise_proposee(): void
    {
        $this->expectCheckoutIn(BillingCurrency::Xof);

        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'association']))
            ->assertRedirect('https://checkout.example/session');
    }

    public function test_une_devise_inconnue_est_refusee(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'association']), ['currency' => 'GBP'])
            ->assertSessionHasErrors('currency');
    }

    public function test_une_devise_connue_mais_non_proposee_est_refusee(): void
    {
        config(['convive.billing.currencies' => ['EUR']]);

        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'association']), ['currency' => 'USD'])
            ->assertSessionHasErrors('currency');
    }

    public function test_un_plan_sans_prix_dans_la_devise_choisie_ne_se_souscrit_pas(): void
    {
        $plan = Plan::ensure(PlanCode::Association);
        $plan->update(['monthly_price_usd' => null]);

        $this->actingAs($this->owner)
            ->post(route('tenants.billing.checkout', [$this->tenant, 'association']), ['currency' => 'USD'])
            ->assertSessionHasErrors('billing');
    }

    public function test_la_devise_d_une_facture_est_transmise_a_l_ecran(): void
    {
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => 3800,
            'currency' => 'EUR',
        ]);

        $this->actingAs($this->owner)
            ->get(route('tenants.billing.show', $this->tenant))
            ->assertInertia(fn ($page) => $page
                ->where('invoices.0.amount', 3800)
                ->where('invoices.0.currency', 'EUR'),
            );
    }
}
