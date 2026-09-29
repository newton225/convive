<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Liste des appareils connectes et deconnexion des autres (SECURITY.md, « Deconnexion et
 * sessions ») : un membre voit ou son compte est ouvert et ferme d'un geste une session qu'il ne
 * reconnait pas, sur le modele de Laravel Jetstream.
 */
class ConnectedDevicesTest extends TestCase
{
    use RefreshDatabase;

    private const Chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    private const IphoneSafari = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    private function sessions(): Builder
    {
        return DB::connection(config('tenancy.database.central_connection'))->table('sessions');
    }

    private function openSession(User $user, string $userAgent, string $ip = '196.47.12.8'): string
    {
        $id = Str::random(40);

        $this->sessions()->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->subMinutes(5)->getTimestamp(),
        ]);

        return $id;
    }

    public function test_l_ecran_securite_liste_les_appareils_du_membre_sans_exposer_les_identifiants_de_session(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $sessionId = $this->openSession($user, self::IphoneSafari);
        $this->openSession($stranger, self::Chrome);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('devices', 1)
            ->where('devices.0.browser', 'Safari')
            ->where('devices.0.platform', 'iOS')
            ->where('devices.0.mobile', true)
            ->where('devices.0.ipAddress', '196.47.12.8')
            ->where('devices.0.isCurrent', false)
            ->missing('devices.0.id'),
        );

        $this->assertStringNotContainsString($sessionId, (string) $response->getContent());
    }

    public function test_deconnecter_les_autres_appareils_ferme_leurs_sessions_et_seulement_les_siennes(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $this->openSession($user, self::IphoneSafari);
        $this->openSession($user, self::Chrome);
        $this->openSession($stranger, self::Chrome);

        $this->actingAs($user)
            ->delete(route('other-sessions.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(0, $this->sessions()->where('user_id', $user->id)->where('id', '!=', session()->getId())->count());
        $this->assertSame(1, $this->sessions()->where('user_id', $stranger->id)->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'account.other_sessions_closed', 'causer_id' => $user->id]);
    }

    public function test_un_mauvais_mot_de_passe_ne_ferme_aucune_session(): void
    {
        $user = User::factory()->create();
        $otherDevice = $this->openSession($user, self::IphoneSafari);

        $this->actingAs($user)
            ->delete(route('other-sessions.destroy'), ['password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrors('password');

        // La session de la requete elle-meme est aussi ecrite en base (pilote `database`) : on
        // verifie celle de l'autre appareil, pas un total qui l'inclurait.
        $this->assertTrue($this->sessions()->where('id', $otherDevice)->exists());
    }

    public function test_un_visiteur_non_connecte_est_renvoye_vers_la_connexion(): void
    {
        $this->delete(route('other-sessions.destroy'), ['password' => 'password'])
            ->assertRedirect(route('login'));
    }
}
