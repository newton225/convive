<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateStarterUnits;
use App\Actions\Tenants\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Health\Checks\OrphanTenantDatabasesCheck;
use App\Support\ScopedSqliteDatabaseManager;
use App\Support\TenantDatabaseFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Health\Enums\Status;
use Tests\TestCase;

/**
 * Bases d'organisation orphelines (incident du 2026-10-04) : une creation d'organisation echouee le
 * 2026-10-02 avait laisse `tenant16.sqlite` sans organisation. SQLite reprenant le numero 16 pour la
 * suivante, toute creation de compte echouait ensuite (« Database tenant16.sqlite already exists »).
 * Deux protections : la base est effacee quand la creation echoue, et l'ecran de sante technique
 * signale tout fichier de base qui n'appartient a aucune organisation.
 */
class TenantDatabaseOrphansTest extends TestCase
{
    use RefreshDatabase;

    private function databaseFiles(): int
    {
        return count(glob(ScopedSqliteDatabaseManager::directory().'/tenant*.sqlite') ?: []);
    }

    public function test_une_creation_echouee_ne_laisse_aucune_base_et_la_suivante_reussit(): void
    {
        $user = User::factory()->create();
        $before = $this->databaseFiles();

        $this->mock(CreateStarterUnits::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('panne simulee')));

        try {
            app(CreateTenant::class)->handle($user, 'Association en panne');
            $this->fail('La creation aurait du echouer.');
        } catch (RuntimeException) {
            //
        }

        $this->assertSame($before, $this->databaseFiles());
        $this->assertSame(0, Tenant::where('name', 'Association en panne')->count());

        $this->app->forgetInstance(CreateStarterUnits::class);
        $this->app->offsetUnset(CreateStarterUnits::class);

        $tenant = app(CreateTenant::class)->handle($user, 'Association suivante');

        $this->assertTrue($tenant->database()->manager()->databaseExists($tenant->database()->getName()));
    }

    public function test_un_fichier_de_base_sans_organisation_est_signale(): void
    {
        $owner = User::factory()->create();
        app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->assertSame(Status::ok()->value, OrphanTenantDatabasesCheck::new()->run()->status->value);

        $stray = ScopedSqliteDatabaseManager::directory().DIRECTORY_SEPARATOR.'tenant999.sqlite';
        file_put_contents($stray, '');

        try {
            $this->assertSame(['tenant999.sqlite'], TenantDatabaseFiles::orphans());
            $this->assertSame(Status::failed()->value, OrphanTenantDatabasesCheck::new()->run()->status->value);
        } finally {
            @unlink($stray);
        }
    }

    public function test_la_base_d_une_organisation_en_corbeille_n_est_pas_orpheline(): void
    {
        // Une organisation supprimee reste trente jours en corbeille, recuperable : sa base aussi.
        $owner = User::factory()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association en corbeille');
        $tenant->delete();

        $this->assertSame([], TenantDatabaseFiles::orphans());
    }
}
