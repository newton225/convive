<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Backup\DatabaseSnapshots;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PDO;
use Tests\TestCase;

/**
 * La copie des bases avant l'archivage (CLAUDE.md, « Sauvegardes ») : la base centrale et celle de
 * chaque organisation, lisibles, puis retirees du serveur.
 *
 * `DatabaseMigrations` et non `RefreshDatabase` : ce dernier enveloppe chaque test dans une
 * transaction, et SQLite refuse `VACUUM` a l'interieur d'une transaction. En production, la
 * commande de sauvegarde n'en ouvre aucune.
 */
class DatabaseSnapshotsTest extends TestCase
{
    use DatabaseMigrations;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
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
}
