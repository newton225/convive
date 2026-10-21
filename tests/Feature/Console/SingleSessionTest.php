<?php

namespace Tests\Feature\Console;

use App\Models\ConsoleActionLog;
use App\Models\User;
use App\Settings\ProtectionSettings;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * L'interrupteur « une seule session par compte » (decision du proprietaire du projet, 2026-10-21),
 * regle depuis l'ecran Securite de la console : ferme au depart, il ferme les autres sessions du meme
 * compte a la connexion suivante quand il est ouvert.
 */
class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
    }

    /**
     * Ouvre une session « database » dans la requete courante, comme en production, et y rend
     * `$user` connecte : la ligne de la session qui vient de se connecter est `$current`.
     */
    private function connectOnDatabaseSessions(User $user, string $current): void
    {
        $store = app('session')->driver('database');
        $store->setId($current);
        $store->start();
        request()->setLaravelSession($store);

        Event::dispatch(new Login('web', $user, false));
    }

    private function openSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_l_interrupteur_est_ferme_par_defaut(): void
    {
        $this->assertFalse(app(ProtectionSettings::class)->single_session);
    }

    public function test_l_ecran_securite_montre_l_etat_de_l_interrupteur(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.security'))
            ->assertInertia(fn (Assert $page) => $page->where('singleSession', false));
    }

    public function test_un_fondateur_ouvre_l_interrupteur_et_le_journal_garde_l_avant_et_l_apres(): void
    {
        $this->actingAs($this->founder)
            ->put(route('console.security.single-session.update'), ['single_session' => true])
            ->assertRedirect(route('console.security'));

        $this->assertTrue(app(ProtectionSettings::class)->single_session);

        $entry = ConsoleActionLog::where('type', 'single_session_updated')->sole();

        $this->assertFalse($entry->properties['old']['single_session']);
        $this->assertTrue($entry->properties['attributes']['single_session']);
        $this->assertSame($this->founder->id, $entry->actor_id);
    }

    public function test_une_valeur_inchangee_n_ecrit_rien_au_journal(): void
    {
        $this->actingAs($this->founder)
            ->put(route('console.security.single-session.update'), ['single_session' => false])
            ->assertRedirect(route('console.security'));

        $this->assertSame(0, ConsoleActionLog::where('type', 'single_session_updated')->count());
    }

    public function test_une_valeur_absente_est_refusee(): void
    {
        $this->actingAs($this->founder)
            ->put(route('console.security.single-session.update'), [])
            ->assertSessionHasErrors('single_session');
    }

    public function test_un_compte_sans_acces_a_la_console_est_refuse(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->put(route('console.security.single-session.update'), ['single_session' => true])
            ->assertNotFound();

        $this->assertFalse(app(ProtectionSettings::class)->single_session);
    }

    public function test_ouvert_une_nouvelle_connexion_ferme_les_autres_sessions_du_compte(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->openSession($user, 'ancienne-session');
        $this->openSession($other, 'session-d-un-autre-compte');

        $settings = app(ProtectionSettings::class);
        $settings->single_session = true;
        $settings->save();

        $this->connectOnDatabaseSessions($user, 'nouvelle-session');

        $this->assertDatabaseMissing('sessions', ['id' => 'ancienne-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'session-d-un-autre-compte']);
    }

    public function test_ferme_les_connexions_simultanees_restent_permises(): void
    {
        $user = User::factory()->create();
        $this->openSession($user, 'ancienne-session');

        $this->connectOnDatabaseSessions($user, 'nouvelle-session');

        $this->assertDatabaseHas('sessions', ['id' => 'ancienne-session']);
    }
}
