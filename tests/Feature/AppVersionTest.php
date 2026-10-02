<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Models\User;
use App\Support\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * La version de l'application s'affiche dans le back-office : un numero choisi par l'editeur, suivi
 * de la date de la livraison en service (decision du proprietaire du projet, 2026-10-02). Elle
 * n'est partagee qu'avec un membre connecte : un visiteur anonyme n'a pas a savoir quelle version
 * tourne.
 */
class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    private string $releasePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Jamais l'estampille de la machine : chaque test ecrit la sienne, a part.
        $this->releasePath = storage_path('framework/testing/release-'.uniqid().'.json');
        config(['convive.version' => '2.4.1', 'convive.release_path' => $this->releasePath]);
    }

    protected function tearDown(): void
    {
        File::delete($this->releasePath);

        parent::tearDown();
    }

    private function member(): User
    {
        $owner = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($owner, 'Association Convive');

        return $owner;
    }

    public function test_un_membre_connecte_recoit_la_version_de_l_application(): void
    {
        $this->actingAs($this->member())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('appVersion', '2.4.1'));
    }

    public function test_un_visiteur_anonyme_ne_recoit_ni_la_version_ni_la_livraison(): void
    {
        Release::stamp('0741e99');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('appVersion', null)
                ->where('appRelease', null),
            );
    }

    public function test_sans_livraison_estampillee_seul_le_numero_s_affiche(): void
    {
        $this->actingAs($this->member())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('appRelease', null));
    }

    public function test_la_commande_de_livraison_note_la_date_et_la_modification(): void
    {
        $this->freezeSecond();
        Process::fake(['*' => Process::result("0741e99\n")]);

        $this->assertSame(0, Artisan::call('convive:release'));

        $this->assertSame(
            ['releasedAt' => now()->toISOString(), 'commit' => '0741e99'],
            Release::current(),
        );

        $this->actingAs($this->member())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('appVersion', '2.4.1')
                ->where('appRelease.commit', '0741e99')
                ->where('appRelease.releasedAt', now()->toISOString()),
            );
    }

    public function test_l_identifiant_peut_etre_donne_quand_git_n_est_pas_sur_le_serveur(): void
    {
        Process::fake(['*' => Process::result('', 'git: command not found', 127)]);

        $this->assertSame(0, Artisan::call('convive:release'));
        $this->assertNull(Release::current()['commit'] ?? null);
        $this->assertNotNull(Release::current());

        $this->assertSame(0, Artisan::call('convive:release', ['--commit' => 'abc1234']));
        $this->assertSame('abc1234', Release::current()['commit'] ?? null);
    }

    public function test_une_estampille_illisible_est_ignoree(): void
    {
        File::ensureDirectoryExists(dirname($this->releasePath));
        File::put($this->releasePath, 'pas du json');

        $this->assertNull(Release::current());
    }
}
