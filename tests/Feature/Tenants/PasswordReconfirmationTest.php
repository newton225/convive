<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SECURITY.md H8 et M5 : l'identite legale, le sous-domaine, les profils, l'abonnement et les
 * membres redemandent le mot de passe avant toute modification, comme les comptes de versement.
 */
class PasswordReconfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $confirmsPasswordOnActingAs = false;

    private function tenantOwnedBy(User $user): Tenant
    {
        return app(CreateTenant::class)->handle($user, 'Association Convive');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function sensitiveRoutes(): array
    {
        return [
            'identite legale' => ['patch', 'tenants.organisation.legal', ''],
            'sous-domaine' => ['patch', 'tenants.organisation.subdomain', ''],
            'nouveau profil' => ['post', 'tenants.profiles.store', ''],
            'abonnement, annulation' => ['post', 'tenants.billing.cancel', ''],
            'abonnement, moyen de paiement' => ['post', 'tenants.billing.payment-method', ''],
        ];
    }

    #[DataProvider('sensitiveRoutes')]
    public function test_la_modification_sans_mot_de_passe_recent_renvoie_vers_la_confirmation(string $method, string $route): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->{$method}(route($route, $tenant))
            ->assertRedirect(route('password.confirm'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pagesWithSensitiveActions(): array
    {
        return [
            'organisation' => ['tenants.organisation.edit'],
            'membres' => ['tenants.edit'],
            'abonnement' => ['tenants.billing.show'],
            'acces du support' => ['tenants.support-access.show'],
        ];
    }

    #[DataProvider('pagesWithSensitiveActions')]
    public function test_l_ecran_demande_le_mot_de_passe_avant_la_saisie(string $route): void
    {
        // Demande a l'envoi seulement, la confirmation ramenait a un ecran vide : tout ce qui
        // avait ete saisi etait perdu (constate le 2026-10-04).
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->get(route($route, $tenant))
            ->assertRedirect(route('password.confirm'));
    }

    #[DataProvider('sensitiveRoutes')]
    public function test_le_mot_de_passe_recent_laisse_passer_jusqu_a_la_validation(string $method, string $route): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner);
        $this->session(['auth.password_confirmed_at' => now()->getTimestamp()]);

        $response = $this->{$method}(route($route, $tenant));

        $this->assertNotSame(route('password.confirm'), $response->headers->get('Location'));
    }
}
