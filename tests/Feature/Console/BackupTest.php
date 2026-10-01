<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ConsoleProfile;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Les sauvegardes (README ecran 31) : un etat lu sur la destination, et un lancement a la demande
 * reserve aux profils qui ouvrent la sante technique. La copie des bases est dans
 * `DatabaseSnapshotsTest`.
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
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);
        $stranger = User::factory()->withTwoFactor()->create();

        // Apres la creation des comptes : la fabrique ouvre un espace personnel, donc rejoue des
        // migrations par Artisan.
        Artisan::shouldReceive('call')->never();

        $this->actingAs($support)
            ->post(route('console.health.backup'))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('console.health.backup'))
            ->assertNotFound();
    }
}
