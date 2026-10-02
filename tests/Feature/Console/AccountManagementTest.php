<?php

namespace Tests\Feature\Console;

use App\Enums\ConsoleProfile;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\User;
use App\Notifications\Console\TwoFactorResetByEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Les comptes dans la console (README section 3) : l'equipe Convive retrouve une personne, bloque
 * un compte compromis, reinitialise une double authentification perdue. Aucun geste ne donne acces
 * au compte, et chacun s'ecrit au journal central.
 */
class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
        $this->target = User::factory()->withTwoFactor()->create(['name' => 'Aya Kouassi', 'email' => 'aya@example.test']);
    }

    public function test_la_recherche_retrouve_une_personne_par_son_nom_ou_son_adresse(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.accounts.index', ['q' => 'kouassi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('console/accounts')
                ->has('results', 1)
                ->where('results.0.email', 'aya@example.test')
                ->where('results.0.hasTwoFactor', true)
                // Jamais le mot de passe ni le secret du second facteur.
                ->missing('results.0.password')
                ->missing('results.0.two_factor_secret'),
            );
    }

    public function test_sans_recherche_precise_aucun_compte_n_est_liste(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.accounts.index'))
            ->assertInertia(fn (Assert $page) => $page->has('results', 0));

        // Deux caracteres, ou des jokers, ne ramenent pas la liste des comptes.
        $this->actingAs($this->founder)
            ->get(route('console.accounts.index', ['q' => 'ay']))
            ->assertInertia(fn (Assert $page) => $page->has('results', 0));

        $this->actingAs($this->founder)
            ->get(route('console.accounts.index', ['q' => '%%%%']))
            ->assertInertia(fn (Assert $page) => $page->has('results', 0));
    }

    public function test_un_compte_bloque_ne_peut_plus_utiliser_l_application(): void
    {
        $this->actingAs($this->founder)
            ->post(route('console.accounts.block', $this->target), ['reason' => 'Compte signale comme compromis par son proprietaire.'])
            ->assertRedirect();

        $this->assertTrue($this->target->fresh()->isBlocked());
        $this->assertTrue(ConsoleActionLog::where('type', 'account_blocked')->where('actor_id', $this->founder->id)->exists());

        $this->actingAs($this->target->fresh())
            ->get(route('tenants.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_un_blocage_exige_un_motif(): void
    {
        $this->actingAs($this->founder)
            ->post(route('console.accounts.block', $this->target), ['reason' => 'court'])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($this->target->fresh()->isBlocked());
    }

    public function test_personne_ne_bloque_son_propre_compte(): void
    {
        $this->actingAs($this->founder)
            ->post(route('console.accounts.block', $this->founder), ['reason' => 'Je me bloque moi-meme par erreur.'])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($this->founder->fresh()->isBlocked());
    }

    public function test_un_compte_debloque_se_reconnecte(): void
    {
        $this->target->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Compte compromis.'])->save();

        $this->actingAs($this->founder)
            ->delete(route('console.accounts.unblock', $this->target))
            ->assertRedirect();

        $this->assertFalse($this->target->fresh()->isBlocked());

        $this->actingAs($this->target->fresh())
            ->get(route('tenants.index'))
            ->assertOk();
    }

    public function test_la_reinitialisation_retire_le_second_facteur_et_previent_la_personne(): void
    {
        Notification::fake();

        $this->actingAs($this->founder)
            ->post(route('console.accounts.two-factor-reset', $this->target))
            ->assertRedirect();

        $target = $this->target->fresh();

        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_confirmed_at);
        // Le mot de passe n'est pas touche.
        $this->assertSame($this->target->password, $target->password);
        Notification::assertSentTo($this->target, TwoFactorResetByEditor::class);
        $this->assertTrue(ConsoleActionLog::where('type', 'two_factor_reset')->exists());
    }

    public function test_seuls_les_fondateurs_gerent_les_comptes(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)->get(route('console.accounts.index'))->assertForbidden();
        $this->actingAs($support)
            ->post(route('console.accounts.block', $this->target), ['reason' => 'Tentative depuis le profil support.'])
            ->assertForbidden();
        $this->actingAs($support)->post(route('console.accounts.two-factor-reset', $this->target))->assertForbidden();

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)->get(route('console.accounts.index'))->assertNotFound();
        $this->actingAs($stranger)
            ->post(route('console.accounts.block', $this->target), ['reason' => 'Tentative depuis un compte ordinaire.'])
            ->assertNotFound();

        $this->assertFalse($this->target->fresh()->isBlocked());
    }
}
