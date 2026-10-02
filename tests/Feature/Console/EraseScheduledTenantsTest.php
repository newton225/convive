<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Models\ConsoleActionLog;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L'effacement d'une organisation a l'echeance de sa suppression programmee (README section 3) :
 * sa base, ses fichiers et son enregistrement central disparaissent, la trace reste au journal
 * central, et rien d'autre n'est touche. L'effacement ne se rattrape pas : chaque borne est testee.
 */
class EraseScheduledTenantsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $due;

    private Tenant $other;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tenant_media');
        Storage::fake('payment_proofs');

        $this->due = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association a effacer');
        $this->other = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association voisine');

        foreach ([$this->due, $this->other] as $tenant) {
            Storage::disk('tenant_media')->put("tenants/{$tenant->id}/logo/1/logo.png", 'logo');
            Storage::disk('payment_proofs')->put("tenants/{$tenant->id}/receipt/1/recu.jpg", 'recu');
        }
    }

    private function schedule(Tenant $tenant, int $daysFromNow): void
    {
        $tenant->forceFill(['deletion_scheduled_at' => now()->addDays($daysFromNow)])->save();
    }

    private function databaseExists(Tenant $tenant): bool
    {
        $database = $tenant->database();

        return $database->manager()->databaseExists($database->getName());
    }

    public function test_a_l_echeance_la_base_les_fichiers_et_l_enregistrement_disparaissent(): void
    {
        $this->schedule($this->due, -1);

        $this->artisan('tenants:erase-scheduled', ['--force' => true])->assertSuccessful();

        $this->assertNull(Tenant::withTrashed()->find($this->due->id));
        $this->assertFalse($this->databaseExists($this->due));
        Storage::disk('tenant_media')->assertMissing("tenants/{$this->due->id}/logo/1/logo.png");
        // Les preuves de paiement portent des donnees personnelles : elles partent aussi.
        Storage::disk('payment_proofs')->assertMissing("tenants/{$this->due->id}/receipt/1/recu.jpg");
        $this->assertSame(0, Membership::where('tenant_id', $this->due->id)->count());
    }

    public function test_le_journal_central_garde_la_trace_de_l_organisation_effacee(): void
    {
        $this->schedule($this->due, -1);

        $this->artisan('tenants:erase-scheduled', ['--force' => true]);

        $entry = ConsoleActionLog::where('type', 'tenant_erased')->sole();

        $this->assertSame('Association a effacer', $entry->organisation);
        $this->assertSame($this->due->id, $entry->tenant_id);
    }

    public function test_une_organisation_voisine_n_est_pas_touchee(): void
    {
        $this->schedule($this->due, -1);

        $this->artisan('tenants:erase-scheduled', ['--force' => true]);

        $this->assertNotNull(Tenant::find($this->other->id));
        $this->assertTrue($this->databaseExists($this->other));
        Storage::disk('tenant_media')->assertExists("tenants/{$this->other->id}/logo/1/logo.png");
        Storage::disk('payment_proofs')->assertExists("tenants/{$this->other->id}/receipt/1/recu.jpg");
    }

    public function test_une_suppression_pas_encore_arrivee_a_echeance_ne_fait_rien(): void
    {
        $this->schedule($this->due, 1);

        $this->artisan('tenants:erase-scheduled', ['--force' => true])->assertSuccessful();

        $this->assertNotNull(Tenant::find($this->due->id));
        $this->assertTrue($this->databaseExists($this->due));
        $this->assertSame(0, ConsoleActionLog::where('type', 'tenant_erased')->count());
    }

    public function test_une_organisation_sans_suppression_programmee_n_est_jamais_effacee(): void
    {
        $this->artisan('tenants:erase-scheduled', ['--force' => true])->assertSuccessful();

        $this->assertSame(2, Tenant::whereIn('id', [$this->due->id, $this->other->id])->count());
    }

    public function test_sans_l_option_la_commande_liste_sans_rien_effacer(): void
    {
        $this->schedule($this->due, -1);

        $this->artisan('tenants:erase-scheduled')->assertSuccessful();

        $this->assertNotNull(Tenant::find($this->due->id));
        $this->assertTrue($this->databaseExists($this->due));
        Storage::disk('tenant_media')->assertExists("tenants/{$this->due->id}/logo/1/logo.png");
        $this->assertSame(0, ConsoleActionLog::where('type', 'tenant_erased')->count());
    }

    public function test_une_suppression_annulee_avant_l_echeance_n_est_pas_effacee(): void
    {
        $this->schedule($this->due, -1);
        $this->due->forceFill(['deletion_scheduled_at' => null])->save();

        $this->artisan('tenants:erase-scheduled', ['--force' => true]);

        $this->assertNotNull(Tenant::find($this->due->id));
    }
}
