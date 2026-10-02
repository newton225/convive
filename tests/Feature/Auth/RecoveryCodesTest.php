<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Auth\RecoveryCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Codes de secours de la double authentification (SECURITY.md M5) : haches, a usage unique,
 * denombres. Seule leur empreinte est gardee : ils s'affichent une fois, a leur creation, et
 * personne, pas meme l'application, ne peut les relire ensuite.
 */
class RecoveryCodesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $confirmsPasswordOnActingAs = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);
    }

    /**
     * @return array<int, string>
     */
    private function stored(User $user): array
    {
        return json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
    }

    private function challenge(User $user, string $code): void
    {
        $this->withSession(['login.id' => $user->id, 'login.remember' => false])
            ->post(route('two-factor.login.store'), ['recovery_code' => $code]);
    }

    public function test_activer_la_double_authentification_ne_garde_que_l_empreinte_des_codes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.enable'));

        $stored = $this->stored($user);
        $fresh = session(RecoveryCodes::SessionKey);

        $this->assertCount(8, $stored);
        $this->assertCount(8, $fresh);

        foreach ($stored as $entry) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $entry);
            $this->assertNotContains($entry, $fresh);
        }

        $this->assertSame(array_map(RecoveryCodes::hash(...), $fresh), $stored);
    }

    public function test_regenerer_les_codes_ne_garde_que_leur_empreinte(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.regenerate-recovery-codes'));

        $this->assertCount(8, $this->stored($user));
        $this->assertSame(8, RecoveryCodes::remaining($user->fresh()));

        foreach ($this->stored($user) as $entry) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $entry);
        }
    }

    public function test_les_nouveaux_codes_s_affichent_une_seule_fois(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.regenerate-recovery-codes'));

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('freshRecoveryCodes', 8)
                ->where('recoveryCodesRemaining', 8),
            );

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('freshRecoveryCodes', null)
                ->where('recoveryCodesRemaining', 8),
            );
    }

    public function test_les_codes_attendent_la_confirmation_pour_s_afficher(): void
    {
        // Affiches avant que l'activation soit confirmee, ils seraient perdus : l'ecran ne montre
        // les codes qu'une fois la double authentification en service.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.enable'));

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('freshRecoveryCodes', null));

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page->has('freshRecoveryCodes', 8));
    }

    public function test_un_code_de_secours_ouvre_la_session_et_ne_sert_qu_une_fois(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        RecoveryCodes::replace($user, ['premier-code', 'second-code']);

        $this->challenge($user, 'premier-code');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, RecoveryCodes::remaining($user->fresh()));
        $this->assertSame([RecoveryCodes::hash('second-code')], $this->stored($user));

        auth()->logout();

        $this->challenge($user, 'premier-code');

        $this->assertGuest();
    }

    public function test_un_code_inconnu_est_refuse(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        RecoveryCodes::replace($user, ['premier-code']);

        $this->challenge($user, 'autre-code');

        $this->assertGuest();
        $this->assertSame(1, RecoveryCodes::remaining($user->fresh()));
    }

    public function test_l_empreinte_gardee_en_base_ne_vaut_pas_code(): void
    {
        // Qui volerait la base ne doit pas pouvoir se servir de ce qu'il y lit.
        $user = User::factory()->withTwoFactor()->create();
        RecoveryCodes::replace($user, ['premier-code']);

        $this->challenge($user, RecoveryCodes::hash('premier-code'));

        $this->assertGuest();
    }

    public function test_un_code_enregistre_en_clair_avant_cette_regle_sert_encore_une_fois(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'two_factor_recovery_codes' => encrypt(json_encode(['ancien-code-en-clair', 'autre-ancien-code'])),
        ]);

        $this->assertSame(2, RecoveryCodes::remaining($user));

        $this->challenge($user, 'ancien-code-en-clair');

        $this->assertAuthenticatedAs($user);
        // Ce qui reste est desormais garde sous forme d'empreinte.
        $this->assertSame([RecoveryCodes::hash('autre-ancien-code')], $this->stored($user));
    }

    public function test_un_compte_sans_code_n_en_compte_aucun(): void
    {
        $this->assertSame(0, RecoveryCodes::remaining(User::factory()->create()));
    }
}
