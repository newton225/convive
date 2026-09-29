<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La vitrine du produit sur la page d'accueil.
 */
class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visiteur_voit_la_vitrine_avec_les_trois_plans(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('welcome')
                ->has('plans', 3)
                ->where('plans.0.code', 'essential')
                ->where('plans.1.code', 'association')
                ->where('plans.1.highlighted', true)
                ->where('plans.2.code', 'institution')
                ->where('defaultCurrency', 'XOF'),
            );
    }

    public function test_les_prix_viennent_de_la_table_des_plans(): void
    {
        $this->get('/');

        Plan::where('code', 'association')->update(['monthly_price' => 30000]);

        $this->get('/')->assertInertia(fn ($page) => $page->where('plans.1.prices.XOF', 30000));
    }

    public function test_un_plan_sur_devis_n_a_pas_de_prix(): void
    {
        $this->get('/')->assertInertia(fn ($page) => $page->where('plans.2.prices.XOF', null));
    }

    public function test_un_membre_connecte_est_renvoye_vers_son_espace_et_ne_voit_pas_la_vitrine(): void
    {
        $user = User::factory()->create();
        $tenant = $user->personalTenant();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect("/{$tenant->slug}/dashboard");
    }

    public function test_un_membre_connecte_est_renvoye_vers_son_organisation_courante(): void
    {
        $user = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($user, 'Association Convive');
        $user->switchTenant($tenant);

        $this->actingAs($user->fresh())
            ->get('/')
            ->assertRedirect('/association-convive/dashboard');
    }

    public function test_un_membre_connecte_ne_revoit_pas_la_connexion_ni_l_inscription(): void
    {
        $user = User::factory()->create();
        $tenant = $user->personalTenant();

        $this->actingAs($user)->get(route('login'))->assertRedirect("/{$tenant->slug}/dashboard");
        $this->actingAs($user)->get(route('register'))->assertRedirect("/{$tenant->slug}/dashboard");
    }

    public function test_apres_deconnexion_la_vitrine_redevient_visible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'));

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('welcome'));
    }

    public function test_la_vitrine_s_affiche_en_anglais_sur_demande(): void
    {
        $this->get('/?lang=en')->assertOk()->assertInertia(fn ($page) => $page
            ->where('locale', 'en')
            ->where('translations.site.nav.pricing', 'Pricing'),
        );
    }

    public function test_la_vitrine_est_en_francais_par_defaut(): void
    {
        // Une langue explicite et non servie : sans en-tete, `Request::create()` de Symfony en pose
        // un par defaut (`en-us`), et le test simulerait sans le dire un navigateur anglophone.
        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->get('/')->assertInertia(fn ($page) => $page
            ->where('locale', 'fr')
            ->where('translations.site.nav.pricing', 'Tarifs'),
        );
    }
}
