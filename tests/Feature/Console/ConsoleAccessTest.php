<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Console d'exploitation (README §3 « Console d'exploitation », ecrans 27 a 34), phase interface :
 * chaque ecran s'ouvre pour un operateur de la console, et reste introuvable (404) pour tout autre
 * compte, sans rien reveler de son existence.
 */
class ConsoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
     */
    public static function screens(): array
    {
        return [
            'organisations' => ['console.organisations.index', [], 'console/organisations'],
            'fiche organisation' => ['console.organisations.show', ['organisation' => 'eglise-bethel'], 'console/organisation'],
            'recouvrement' => ['console.recovery', [], 'console/recovery'],
            'plans' => ['console.plans', [], 'console/plans'],
            'sante technique' => ['console.health', [], 'console/health'],
            'vitrine' => ['console.showcase', [], 'console/showcase'],
            'journal central' => ['console.audit', [], 'console/audit'],
            'equipe editeur' => ['console.team', [], 'console/team'],
        ];
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('screens')]
    public function test_un_operateur_de_la_console_ouvre_l_ecran(string $route, array $parameters, string $component): void
    {
        config(['convive.console.operators' => ['exploitation@convive.test']]);
        $operator = User::factory()->withTwoFactor()->create(['email' => 'exploitation@convive.test']);

        $this->actingAs($operator)
            ->get(route($route, $parameters))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('isSample', ! in_array($component, ['console/plans', 'console/team', 'console/audit'], true)),
            );
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('screens')]
    public function test_un_membre_d_organisation_ne_trouve_pas_l_ecran(string $route, array $parameters): void
    {
        config(['convive.console.operators' => ['exploitation@convive.test']]);
        $member = User::factory()->create(['email' => 'membre@convive.test']);

        $this->actingAs($member)
            ->get(route($route, $parameters))
            ->assertNotFound();
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('screens')]
    public function test_un_visiteur_est_renvoye_a_la_connexion(string $route, array $parameters): void
    {
        $this->get(route($route, $parameters))
            ->assertRedirect(route('login'));
    }

    public function test_une_organisation_inconnue_recoit_404(): void
    {
        config(['convive.console.operators' => ['exploitation@convive.test']]);
        $operator = User::factory()->withTwoFactor()->create(['email' => 'exploitation@convive.test']);

        $this->actingAs($operator)
            ->get(route('console.organisations.show', ['organisation' => 'inconnue']))
            ->assertNotFound();
    }

    public function test_seul_un_operateur_recoit_le_lien_vers_la_console(): void
    {
        config(['convive.console.operators' => ['exploitation@convive.test']]);
        $operator = User::factory()->withTwoFactor()->create(['email' => 'exploitation@convive.test']);
        $member = User::factory()->create(['email' => 'membre@convive.test']);

        $this->actingAs($operator)
            ->get(route('console.organisations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canAccessConsole', true));

        $this->actingAs($member)
            ->get(route('tenants.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canAccessConsole', false));
    }
}
