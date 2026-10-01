<?php

namespace Tests\Feature\Console;

use App\Enums\ConsoleProfile;
use App\Models\ConsoleOperator;
use App\Models\User;
use App\Notifications\Console\ConsoleOperatorInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * L'equipe editeur (README ecran 34 et section 3) : un Fondateur invite une personne par son
 * adresse et lui donne un profil, qui ouvre certains ecrans de la console et pas les autres. Les
 * adresses de `convive.console.operators` sont les Fondateurs de depart, que l'ecran ne retire pas.
 */
class ConsoleTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
    }

    private function member(ConsoleProfile $profile, string $email): User
    {
        ConsoleOperator::create(['email' => $email, 'profile' => $profile, 'invited_by_id' => $this->founder->id]);

        return User::factory()->withTwoFactor()->create(['email' => $email]);
    }

    public function test_l_ecran_montre_les_membres_et_les_invitations_en_attente(): void
    {
        $this->member(ConsoleProfile::Support, 'support@convive.test');
        ConsoleOperator::create(['email' => 'attente@convive.test', 'profile' => ConsoleProfile::Accounting]);

        $this->actingAs($this->founder)
            ->get(route('console.team'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('console/team')
                ->where('isSample', false)
                ->has('operators', 2)
                ->where('operators.0.email', 'fondateur@convive.test')
                ->where('operators.0.profile', 'founder')
                ->where('operators.0.removable', false)
                ->where('operators.1.email', 'support@convive.test')
                ->where('operators.1.profile', 'support')
                ->has('invitations', 1)
                ->where('invitations.0.email', 'attente@convive.test'),
            );
    }

    public function test_un_fondateur_invite_un_membre_et_le_previent_par_email(): void
    {
        Notification::fake();

        $this->actingAs($this->founder)
            ->post(route('console.team.store'), ['email' => ' Nouveau@Convive.test ', 'profile' => 'support'])
            ->assertRedirect(route('console.team'));

        $operator = ConsoleOperator::sole();

        $this->assertSame('nouveau@convive.test', $operator->email);
        $this->assertSame(ConsoleProfile::Support, $operator->profile);
        $this->assertSame($this->founder->id, $operator->invited_by_id);

        Notification::assertSentOnDemand(ConsoleOperatorInvited::class);
    }

    public function test_une_adresse_deja_dans_l_equipe_est_refusee(): void
    {
        $this->member(ConsoleProfile::Support, 'support@convive.test');

        $this->actingAs($this->founder)
            ->post(route('console.team.store'), ['email' => 'support@convive.test', 'profile' => 'accounting'])
            ->assertSessionHasErrors('email');

        $this->actingAs($this->founder)
            ->post(route('console.team.store'), ['email' => 'fondateur@convive.test', 'profile' => 'support'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, ConsoleOperator::count());
    }

    public function test_un_profil_inconnu_est_refuse(): void
    {
        $this->actingAs($this->founder)
            ->post(route('console.team.store'), ['email' => 'nouveau@convive.test', 'profile' => 'administrateur'])
            ->assertSessionHasErrors('profile');
    }

    public function test_la_personne_invitee_ouvre_la_console_une_fois_son_compte_cree(): void
    {
        $invited = $this->member(ConsoleProfile::Support, 'support@convive.test');

        $this->actingAs($invited)
            ->get(route('console.organisations.index'))
            ->assertOk();
    }

    public function test_un_profil_n_ouvre_que_ses_ecrans(): void
    {
        $support = $this->member(ConsoleProfile::Support, 'support@convive.test');
        $accounting = $this->member(ConsoleProfile::Accounting, 'compta@convive.test');

        $this->actingAs($support)->get(route('console.organisations.index'))->assertOk();
        $this->actingAs($support)->get(route('console.plans'))->assertForbidden();
        $this->actingAs($support)->get(route('console.recovery'))->assertForbidden();
        $this->actingAs($support)->get(route('console.health'))->assertForbidden();
        $this->actingAs($support)->get(route('console.team'))->assertForbidden();

        $this->actingAs($accounting)->get(route('console.organisations.index'))->assertOk();
        $this->actingAs($accounting)->get(route('console.plans'))->assertOk();
        $this->actingAs($accounting)->get(route('console.recovery'))->assertOk();
        $this->actingAs($accounting)->get(route('console.health'))->assertForbidden();
        $this->actingAs($accounting)->get(route('console.team'))->assertForbidden();
    }

    public function test_seul_un_fondateur_gere_l_equipe(): void
    {
        $support = $this->member(ConsoleProfile::Support, 'support@convive.test');

        $this->actingAs($support)
            ->post(route('console.team.store'), ['email' => 'complice@convive.test', 'profile' => 'founder'])
            ->assertForbidden();

        $this->assertSame(1, ConsoleOperator::count());
    }

    public function test_un_fondateur_retire_un_membre_qui_perd_la_console(): void
    {
        $support = $this->member(ConsoleProfile::Support, 'support@convive.test');
        $operator = ConsoleOperator::sole();

        $this->actingAs($this->founder)
            ->delete(route('console.team.destroy', $operator))
            ->assertRedirect(route('console.team'));

        $this->assertSame(0, ConsoleOperator::count());

        $this->actingAs($support)
            ->get(route('console.organisations.index'))
            ->assertNotFound();
    }

    public function test_un_fondateur_ne_se_retire_pas_lui_meme(): void
    {
        $second = $this->member(ConsoleProfile::Founder, 'second@convive.test');
        $operator = ConsoleOperator::sole();

        $this->actingAs($second)
            ->delete(route('console.team.destroy', $operator))
            ->assertSessionHasErrors('operator');

        $this->assertSame(1, ConsoleOperator::count());
    }

    public function test_sans_double_authentification_la_console_renvoie_a_l_ecran_de_securite(): void
    {
        config(['convive.two_factor.enforced' => true]);

        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $withoutTwoFactor = User::factory()->create(['email' => 'support@convive.test']);

        $this->actingAs($withoutTwoFactor)
            ->get(route('console.organisations.index'))
            ->assertRedirect(route('security.edit'));
    }

    public function test_un_compte_hors_de_l_equipe_ne_trouve_pas_l_ecran(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)->get(route('console.team'))->assertNotFound();
        $this->actingAs($stranger)
            ->post(route('console.team.store'), ['email' => 'moi@convive.test', 'profile' => 'founder'])
            ->assertNotFound();
    }
}
