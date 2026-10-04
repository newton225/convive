<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ConsoleProfile;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ScopedSqliteDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Supprimer une base orpheline depuis l'ecran de sante technique (decision du proprietaire du
 * projet, 2026-10-04) : sans toucher au serveur, apres confirmation, au journal de la console. Jamais
 * la base d'une organisation, meme en corbeille.
 */
class OrphanDatabaseRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private string $stray = 'tenant999.sqlite';

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        file_put_contents($this->path($this->stray), '');
    }

    protected function tearDown(): void
    {
        @unlink($this->path($this->stray));

        parent::tearDown();
    }

    private function path(string $file): string
    {
        return ScopedSqliteDatabaseManager::directory().DIRECTORY_SEPARATOR.$file;
    }

    public function test_l_ecran_liste_les_bases_orphelines(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.health'))
            ->assertInertia(fn (Assert $page) => $page->where('orphanDatabases', [$this->stray]));
    }

    public function test_un_fondateur_supprime_une_base_orpheline_et_c_est_journalise(): void
    {
        $this->actingAs($this->founder)
            ->delete(route('console.health.orphan-databases.destroy', $this->stray))
            ->assertRedirect(route('console.health'));

        $this->assertFileDoesNotExist($this->path($this->stray));
        $this->assertSame(1, ConsoleActionLog::where('type', 'orphan_database_deleted')->count());
    }

    public function test_la_base_d_une_organisation_ne_peut_jamais_etre_supprimee_ainsi(): void
    {
        $tenant = app(CreateTenant::class)->handle(User::factory()->create(), 'Association Convive');
        $name = $tenant->fresh()->tenancy_db_name;
        $tenant->delete();

        $this->actingAs($this->founder)
            ->delete(route('console.health.orphan-databases.destroy', $name))
            ->assertNotFound();

        $this->assertFileExists($this->path($name));
        $this->assertNotNull(Tenant::withTrashed()->find($tenant->id));
    }

    public function test_un_nom_qui_sort_du_dossier_des_bases_est_refuse(): void
    {
        $this->actingAs($this->founder)
            ->delete(route('console.health.orphan-databases.destroy', '..%2Fcentral.sqlite'))
            ->assertNotFound();
    }

    public function test_seul_un_profil_qui_ouvre_la_sante_technique_supprime_une_base_orpheline(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)
            ->delete(route('console.health.orphan-databases.destroy', $this->stray))
            ->assertForbidden();

        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->delete(route('console.health.orphan-databases.destroy', $this->stray))
            ->assertNotFound();

        $this->assertFileExists($this->path($this->stray));
    }
}
