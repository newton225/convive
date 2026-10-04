<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ScopedSqliteDatabaseManager;
use App\Support\TenantDatabaseName;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Le nom du fichier de base d'une organisation (decision du proprietaire du projet, 2026-10-04) :
 * `tenant_<ULID>.sqlite`, tire au hasard a la creation et garde pour toujours, plutot que le numero
 * de l'organisation, que SQLite peut redonner a une autre (incident du meme jour).
 */
class TenantDatabaseNameTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Str::createUlidsNormally();

        parent::tearDown();
    }

    public function test_une_nouvelle_organisation_recoit_un_nom_aleatoire_et_non_son_numero(): void
    {
        $tenant = app(CreateTenant::class)->handle(User::factory()->create(), 'Association Convive');

        $name = $tenant->fresh()->tenancy_db_name;

        $this->assertMatchesRegularExpression('/^tenant_[0-9a-z]{26}\.sqlite$/', $name);
        $this->assertStringNotContainsString((string) $tenant->id, substr($name, 0, 8));
        $this->assertFileExists(ScopedSqliteDatabaseManager::directory().DIRECTORY_SEPARATOR.$name);
    }

    public function test_deux_organisations_n_ont_jamais_le_meme_nom(): void
    {
        $owner = User::factory()->create();

        $first = app(CreateTenant::class)->handle($owner, 'Premiere');
        $second = app(CreateTenant::class)->handle($owner, 'Seconde');

        $this->assertNotSame($first->fresh()->tenancy_db_name, $second->fresh()->tenancy_db_name);
    }

    public function test_un_nom_deja_pris_par_un_fichier_est_tire_de_nouveau(): void
    {
        $taken = '01jxk3q7m8v2c9r4t6y8w0z1ab';
        $fresh = '01jxk3q7m8v2c9r4t6y8w0z1ac';
        $takenFile = ScopedSqliteDatabaseManager::directory().DIRECTORY_SEPARATOR.'tenant_'.$taken.'.sqlite';

        file_put_contents($takenFile, 'base deja en place');
        Str::createUlidsUsingSequence([strtoupper($taken), strtoupper($fresh)]);

        try {
            $this->assertSame('tenant_'.$fresh.'.sqlite', TenantDatabaseName::generate());
            $this->assertSame('base deja en place', file_get_contents($takenFile));
        } finally {
            @unlink($takenFile);
        }
    }

    public function test_un_nom_deja_enregistre_pour_une_organisation_est_tire_de_nouveau(): void
    {
        $taken = '01jxk3q7m8v2c9r4t6y8w0z1ad';
        $fresh = '01jxk3q7m8v2c9r4t6y8w0z1ae';

        $tenant = app(CreateTenant::class)->handle(User::factory()->create(), 'Association Convive');
        Tenant::whereKey($tenant->id)->update(['tenancy_db_name' => 'tenant_'.$taken.'.sqlite']);

        Str::createUlidsUsingSequence([strtoupper($taken), strtoupper($fresh)]);

        $this->assertSame('tenant_'.$fresh.'.sqlite', TenantDatabaseName::generate());
    }

    public function test_une_organisation_existante_garde_son_ancien_nom(): void
    {
        // Les organisations ouvertes avant le changement gardent leur fichier `tenantN.sqlite` : leur
        // nom est enregistre, rien ne le recalcule.
        $tenant = app(CreateTenant::class)->handle(User::factory()->create(), 'Association Convive');
        $name = $tenant->fresh()->tenancy_db_name;

        $this->assertSame($name, Tenant::find($tenant->id)->database()->getName());
        $this->assertSame($name, Tenant::find($tenant->id)->database()->getName());
    }

    public function test_la_base_centrale_refuse_deux_organisations_avec_le_meme_fichier(): void
    {
        $owner = User::factory()->create();
        $first = app(CreateTenant::class)->handle($owner, 'Premiere');
        $second = app(CreateTenant::class)->handle($owner, 'Seconde');

        $this->expectException(UniqueConstraintViolationException::class);

        Tenant::whereKey($second->id)->update(['tenancy_db_name' => $first->fresh()->tenancy_db_name]);
    }
}
