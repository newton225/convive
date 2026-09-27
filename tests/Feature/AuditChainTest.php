<?php

namespace Tests\Feature;

use App\Actions\Audit\PurgeAuditLog;
use App\Actions\Tenants\CreateTenant;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\AuditChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * Journal d'audit inalterable (SECURITY.md M6) : chaque entree porte l'empreinte de la precedente,
 * une modification ou une suppression hors de la purge tracee se detecte, et l'application elle-meme
 * refuse de reecrire une entree.
 */
class AuditChainTest extends TestCase
{
    use RefreshDatabase;

    private function log(string $description): AuditEntry
    {
        activity()->log($description);

        return AuditEntry::query()->latest('id')->firstOrFail();
    }

    public function test_chaque_entree_porte_l_empreinte_de_la_precedente(): void
    {
        $first = $this->log('premiere');
        $second = $this->log('seconde');

        $this->assertNotNull($first->hash);
        $this->assertSame($first->hash, $second->previous_hash);
        $this->assertNotSame($first->hash, $second->hash);
        $this->assertNull(AuditChain::firstBrokenEntry());
    }

    public function test_une_entree_modifiee_directement_en_base_est_detectee(): void
    {
        $this->log('premiere');
        $tampered = $this->log('seconde');
        $this->log('troisieme');

        DB::table('activity_log')->where('id', $tampered->id)->update(['description' => 'effacee']);

        $this->assertSame($tampered->id, AuditChain::firstBrokenEntry());
    }

    public function test_une_entree_supprimee_directement_en_base_est_detectee(): void
    {
        $this->log('premiere');
        $deleted = $this->log('seconde');
        $next = $this->log('troisieme');

        DB::table('activity_log')->where('id', $deleted->id)->delete();

        $this->assertSame($next->id, AuditChain::firstBrokenEntry());
    }

    public function test_l_application_refuse_de_modifier_une_entree(): void
    {
        $entry = $this->log('premiere');

        $this->expectException(LogicException::class);

        $entry->update(['description' => 'reecrite']);
    }

    public function test_l_application_refuse_de_supprimer_une_entree(): void
    {
        $entry = $this->log('premiere');

        $this->expectException(LogicException::class);

        $entry->delete();
    }

    public function test_la_purge_a_24_mois_est_tracee_et_laisse_une_chaine_verifiable(): void
    {
        $this->travelTo(now()->subMonths(25));
        $this->log('ancienne');
        $this->log('ancienne aussi');
        $this->travelBack();

        $this->log('recente');

        $purged = app(PurgeAuditLog::class)->handle();

        $this->assertSame(2, $purged);
        $this->assertDatabaseMissing('activity_log', ['description' => 'ancienne']);
        $this->assertDatabaseHas('activity_log', ['description' => 'audit.purged']);
        $this->assertNull(AuditChain::firstBrokenEntry());
    }

    public function test_la_purge_d_un_locataire_se_fait_dans_sa_propre_base(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $tenant->asCurrent(function () {
            $this->travelTo(now()->subMonths(25));
            activity()->log('ancienne du locataire');
            $this->travelBack();

            $this->assertSame(1, app(PurgeAuditLog::class)->handle());
            $this->assertNull(AuditChain::firstBrokenEntry());
        });
    }
}
