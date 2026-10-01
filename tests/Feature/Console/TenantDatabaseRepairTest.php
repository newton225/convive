<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ConsoleProfile;
use App\Models\ConsoleOperator;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Console\TenantDatabaseHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La sante des bases d'organisation (README ecran 31) : la console signale une base absente ou en
 * retard de migrations, et un Fondateur la repare. Un locataire sans ses migrations est un
 * locataire casse (CLAUDE.md, « Multi-locataire »).
 */
class TenantDatabaseRepairTest extends TestCase
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

        TenantDatabaseHealth::forget();
    }

    private function forgetLastMigration(): string
    {
        return $this->tenant->run(function () {
            $last = DB::table('migrations')->orderByDesc('id')->first();
            DB::table('migrations')->where('id', $last->id)->delete();

            return $last->migration;
        });
    }

    public function test_une_base_a_jour_n_est_pas_signalee(): void
    {
        $this->assertNull(TenantDatabaseHealth::of($this->tenant));

        $this->actingAs($this->founder)
            ->get(route('console.health'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('databases', []));
    }

    public function test_une_base_en_retard_de_migrations_est_signalee(): void
    {
        $this->forgetLastMigration();

        $issue = TenantDatabaseHealth::of($this->tenant);

        $this->assertSame('pending_migrations', $issue['issue'] ?? null);
        $this->assertSame(1, $issue['pendingMigrations'] ?? null);

        $this->actingAs($this->founder)
            ->get(route('console.health'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('databases', 1)
                ->where('databases.0.slug', $this->tenant->slug)
                ->where('databases.0.issue', 'pending_migrations'),
            );
    }

    public function test_une_base_absente_est_signalee(): void
    {
        $this->tenant->database()->manager()->deleteDatabase($this->tenant);

        $this->assertSame('missing_database', TenantDatabaseHealth::of($this->tenant)['issue'] ?? null);
    }

    public function test_un_fondateur_recree_une_base_absente(): void
    {
        $this->tenant->database()->manager()->deleteDatabase($this->tenant);

        $this->actingAs($this->founder)
            ->post(route('console.health.databases.migrate', $this->tenant))
            ->assertRedirect(route('console.health'));

        $this->assertNull(TenantDatabaseHealth::of($this->tenant));
    }

    public function test_seul_un_profil_qui_ouvre_la_sante_technique_repare_une_base(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)
            ->post(route('console.health.databases.migrate', $this->tenant))
            ->assertForbidden();

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->post(route('console.health.databases.migrate', $this->tenant))
            ->assertNotFound();
    }
}
