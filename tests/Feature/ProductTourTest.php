<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ProductTour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Les visites guidees du back-office : chacune ne demarre d'elle-meme qu'une fois par membre, quel
 * que soit l'appareil, d'ou un suivi en base plutot que dans le navigateur.
 */
class ProductTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_membre_marque_une_visite_comme_terminee(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->post(route('product-tours.complete', ProductTour::Welcome->value))
            ->assertRedirect();

        $this->assertSame([ProductTour::Welcome->value], $user->fresh()->completed_tours);
    }

    public function test_terminer_deux_fois_la_meme_visite_ne_la_duplique_pas(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)->post(route('product-tours.complete', ProductTour::Proofs->value));
        $this->actingAs($user)->post(route('product-tours.complete', ProductTour::Proofs->value));

        $this->assertSame([ProductTour::Proofs->value], $user->fresh()->completed_tours);
    }

    public function test_une_visite_inconnue_repond_404(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->post(route('product-tours.complete', 'inconnue'))
            ->assertNotFound();
    }

    public function test_un_visiteur_non_connecte_est_renvoye_vers_la_connexion(): void
    {
        $this->post(route('product-tours.complete', ProductTour::Welcome->value))
            ->assertRedirect(route('login'));
    }

    public function test_les_visites_terminees_sont_partagees_avec_chaque_page(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($user, 'Association Convive');
        $user->completeTour(ProductTour::EntryControl);

        $this->actingAs($user)
            ->get(route('tenants.events.index', $tenant))
            ->assertInertia(fn (Assert $page) => $page->where('completedTours', [ProductTour::EntryControl->value]));
    }
}
