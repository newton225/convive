<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ConsoleProfile;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Backup\DatabaseSnapshots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PDO;
use Tests\TestCase;

/**
 * Les sauvegardes (README ecran 31) : une copie lisible de la base centrale et de celle de chaque
 * organisation, un etat lu sur la destination, et un lancement a la demande reserve aux profils qui
 * ouvrent la sante technique.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        $owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        Storage::fake('backups');
    }

    protected function tearDown(): void
    {
        DatabaseSnapshots::clear();

        parent::tearDown();
    }

    private function scalar(PDO $copy, string $sql): mixed
    {
        $statement = $copy->query($sql);

        if ($statement === false) {
            $this->fail("La copie n'a pas repondu a : {$sql}");
        }

        return $statement->fetchColumn();
    }

    public function test_la_copie_contient_la_base_centrale_et_celle_de_chaque_organisation(): void
    {
        $count = DatabaseSnapshots::take();

        $this->assertSame(1 + Tenant::count(), $count);
        $this->assertFileExists(DatabaseSnapshots::directory().DIRECTORY_SEPARATOR.'central.sqlite');

        $copy = new PDO('sqlite:'.DatabaseSnapshots::directory().DIRECTORY_SEPARATOR."tenant{$this->tenant->id}.sqlite");

        $this->assertSame('ok', $this->scalar($copy, 'PRAGMA integrity_check'));
        // Une copie qui s'ouvre et porte le schema de l'organisation, pas un fichier vide.
        $this->assertNotFalse($this->scalar($copy, 'select count(*) from events'));
    }

    public function test_une_organisation_sans_base_n_empeche_pas_de_copier_les_autres(): void
    {
        $this->tenant->database()->manager()->deleteDatabase($this->tenant);

        $count = DatabaseSnapshots::take();

        $this->assertSame(Tenant::count(), $count);
        $this->assertFileDoesNotExist(DatabaseSnapshots::directory().DIRECTORY_SEPARATOR."tenant{$this->tenant->id}.sqlite");
    }

    public function test_les_copies_ne_restent_pas_sur_le_serveur_apres_la_sauvegarde(): void
    {
        DatabaseSnapshots::take();
        DatabaseSnapshots::clear();

        $this->assertDirectoryDoesNotExist(DatabaseSnapshots::directory());
    }

    public function test_l_ecran_dit_qu_aucune_sauvegarde_n_existe_encore(): void
    {
        $this->actingAs($this->founder)
            ->get(route('console.health'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('backup.lastAt', null)
                ->where('backup.count', 0)
                ->where('backup.onApplicationServer', true),
            );
    }

    public function test_l_ecran_lit_la_derniere_archive_sur_la_destination(): void
    {
        Storage::disk('backups')->put('convive/2026-10-01-02-30-00.zip', 'archive');

        $this->actingAs($this->founder)
            ->get(route('console.health'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('backup.count', 1)
                ->where('backup.lastSizeBytes', 7)
                ->whereNot('backup.lastAt', null),
            );
    }

    public function test_un_fondateur_lance_une_sauvegarde_et_le_geste_est_journalise(): void
    {
        Artisan::shouldReceive('call')->once()->with('convive:backup')->andReturn(0);

        $this->actingAs($this->founder)
            ->post(route('console.health.backup'))
            ->assertRedirect(route('console.health'));

        $this->assertTrue(ConsoleActionLog::where('type', 'backup_run')->where('actor_id', $this->founder->id)->exists());
    }

    public function test_seul_un_profil_qui_ouvre_la_sante_technique_lance_une_sauvegarde(): void
    {
        Artisan::shouldReceive('call')->never();

        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)
            ->post(route('console.health.backup'))
            ->assertForbidden();

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->post(route('console.health.backup'))
            ->assertNotFound();
    }
}
