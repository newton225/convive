<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\ConsoleProfile;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\Membership;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\TenantDeletionScheduled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La suppression d'une organisation par son Proprietaire (README section 3) : elle disparait tout
 * de suite pour ses membres, son effacement reel est programme a trente jours, et l'equipe Convive
 * peut la restaurer d'ici la, avec ses membres et leurs profils.
 */
class TenantDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $founder;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        $this->member = User::factory()->withTwoFactor()->create();
        $this->tenant->addMember($this->member, $this->tenant->run(fn () => Profile::where('name', 'Lecture')->firstOrFail()));
    }

    private function deleteByOwner(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('tenants.destroy', $this->tenant), ['name' => $this->tenant->name])
            ->assertRedirect();
    }

    private function trashed(): Tenant
    {
        return Tenant::withTrashed()->findOrFail($this->tenant->id);
    }

    public function test_la_suppression_programme_l_effacement_a_trente_jours(): void
    {
        Notification::fake();
        // A la seconde : la base ne garde pas les microsecondes.
        $this->freezeSecond();

        $this->deleteByOwner();

        $tenant = $this->trashed();

        $this->assertTrue($tenant->trashed());
        $this->assertTrue($tenant->deletion_scheduled_at->equalTo(now()->addDays(30)));
        // Rien n'est efface d'ici la : la base de l'organisation existe toujours.
        $this->assertTrue($tenant->database()->manager()->databaseExists($tenant->database()->getName()));
    }

    public function test_la_suppression_est_inscrite_au_journal_central(): void
    {
        Notification::fake();

        $this->deleteByOwner();

        $entry = ConsoleActionLog::where('type', 'tenant_deleted_by_owner')->sole();

        $this->assertSame($this->tenant->id, $entry->tenant_id);
        $this->assertSame($this->owner->id, $entry->actor_id);
    }

    public function test_les_proprietaires_recoivent_la_date_de_l_effacement(): void
    {
        Notification::fake();

        $this->deleteByOwner();

        Notification::assertSentTo($this->owner, TenantDeletionScheduled::class);
        // Un simple membre n'a pas a decider du sort de l'organisation.
        Notification::assertNotSentTo($this->member, TenantDeletionScheduled::class);
    }

    public function test_l_organisation_supprimee_n_est_plus_accessible_a_ses_membres(): void
    {
        Notification::fake();

        $this->deleteByOwner();

        $this->actingAs($this->member)
            ->get(route('tenants.events.index', $this->tenant->slug))
            ->assertNotFound();

        $this->assertSame(0, Membership::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_l_echeance_l_organisation_supprimee_est_reellement_effacee(): void
    {
        Notification::fake();
        Storage::fake('tenant_media');
        Storage::fake('payment_proofs');

        $this->deleteByOwner();
        $this->travel(31)->days();

        $this->artisan('tenants:erase-scheduled', ['--force' => true])->assertSuccessful();

        $this->assertNull(Tenant::withTrashed()->find($this->tenant->id));
        $this->assertFalse($this->tenant->database()->manager()->databaseExists($this->tenant->database()->getName()));
    }

    public function test_avant_l_echeance_rien_n_est_efface(): void
    {
        Notification::fake();

        $this->deleteByOwner();
        $this->travel(29)->days();

        $this->artisan('tenants:erase-scheduled', ['--force' => true]);

        $this->assertNotNull(Tenant::withTrashed()->find($this->tenant->id));
    }

    public function test_l_equipe_convive_restaure_l_organisation_avec_ses_membres_et_leurs_profils(): void
    {
        Notification::fake();
        $this->deleteByOwner();

        $this->actingAs($this->founder)
            ->delete(route('console.organisations.deletion.cancel', $this->tenant->slug))
            ->assertRedirect();

        $tenant = Tenant::findOrFail($this->tenant->id);

        $this->assertNull($tenant->deletion_scheduled_at);
        $this->assertTrue($this->owner->fresh()->ownsTenant($tenant));
        $this->assertTrue($this->member->fresh()->belongsToTenant($tenant));
        $this->assertFalse($this->member->fresh()->ownsTenant($tenant));
        $this->assertTrue(ConsoleActionLog::where('type', 'tenant_restored')->where('tenant_id', $tenant->id)->exists());

        $this->actingAs($this->member->fresh())
            ->get(route('tenants.events.index', $tenant->slug))
            ->assertOk();
    }

    public function test_la_console_montre_l_organisation_supprimee_et_la_date_de_son_effacement(): void
    {
        Notification::fake();
        $this->deleteByOwner();

        $this->actingAs($this->founder)
            ->get(route('console.organisations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('organisations', fn ($organisations) => collect($organisations)
                    ->contains(fn (array $organisation) => $organisation['slug'] === $this->tenant->slug
                        && $organisation['status'] === 'deleted_by_owner'
                        && $organisation['deletionAt'] !== null)),
            );

        $this->actingAs($this->founder)
            ->get(route('console.organisations.show', $this->tenant->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('organisation.status', 'deleted_by_owner'));
    }

    public function test_seul_un_profil_habilite_restaure_une_organisation(): void
    {
        Notification::fake();
        $this->deleteByOwner();

        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)
            ->delete(route('console.organisations.deletion.cancel', $this->tenant->slug))
            ->assertForbidden();

        // L'ancien Proprietaire ne la restaure pas lui-meme : pour lui, la console n'existe pas.
        $this->actingAs($this->owner)
            ->delete(route('console.organisations.deletion.cancel', $this->tenant->slug))
            ->assertNotFound();

        $this->assertTrue($this->trashed()->trashed());
    }
}
