<?php

namespace Tests\Feature\Console;

use App\Enums\ConsoleProfile;
use App\Models\AuditChainCheck as AuditChainRecord;
use App\Models\AuditEntry;
use App\Models\ConsoleOperator;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Console\SecurityJournal;
use App\Support\Health\Checks\AuditChainCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * L'ecran « Securite » de la console (README section 3) : chaque blocage est journalise et se voit,
 * et une alteration d'un journal d'audit ne reste plus au seul journal du serveur.
 */
class SecurityScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
    }

    private function log(string $description): AuditEntry
    {
        activity()->log($description);

        return AuditEntry::query()->latest('id')->firstOrFail();
    }

    public function test_une_limite_de_debit_atteinte_est_enregistree_une_fois_par_minute(): void
    {
        $request = Request::create('/exports', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9']);

        SecurityJournal::rateLimited($request);
        SecurityJournal::rateLimited($request);

        $event = SecurityEvent::sole();

        $this->assertSame(SecurityEvent::RateLimited, $event->type);
        $this->assertSame('203.0.113.9', $event->ip);

        // Passe la minute, la meme adresse qui insiste est de nouveau comptee.
        $this->travel(61)->seconds();
        SecurityJournal::rateLimited($request);

        $this->assertSame(2, SecurityEvent::count());
    }

    public function test_une_connexion_verrouillee_est_enregistree_avec_l_adresse_visee(): void
    {
        $user = User::factory()->create(['email' => 'cible@convive.test']);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'mauvais-mot-de-passe']);
        }

        $event = SecurityEvent::where('type', SecurityEvent::LoginLockout)->sole();

        $this->assertSame('cible@convive.test', $event->subject);
    }

    public function test_un_journal_intact_est_note_comme_tel(): void
    {
        $this->log('premiere');
        $this->log('seconde');

        $check = SecurityJournal::checkAuditChain();

        $this->assertFalse($check->isBroken());
        $this->assertSame('ok', AuditChainCheck::new()->run()->status->value);
    }

    public function test_un_journal_altere_se_voit_a_l_ecran_et_fait_echouer_le_controle(): void
    {
        $this->log('premiere');
        $tampered = $this->log('seconde');
        $this->log('troisieme');
        DB::table('activity_log')->where('id', $tampered->id)->update(['description' => 'effacee']);

        SecurityJournal::checkAuditChain();

        $this->assertSame($tampered->id, AuditChainRecord::where('scope', AuditChainRecord::Central)->sole()->broken_entry_id);
        $this->assertSame('failed', AuditChainCheck::new()->run()->status->value);

        $this->actingAs($this->founder)
            ->get(route('console.security'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('console/security')
                ->has('chains.broken', 1)
                ->where('chains.broken.0.entryId', $tampered->id),
            );
    }

    public function test_une_nouvelle_verification_efface_une_rupture_reparee(): void
    {
        AuditChainRecord::create(['scope' => AuditChainRecord::Central, 'checked_at' => now()->subDay(), 'broken_entry_id' => 7]);

        SecurityJournal::checkAuditChain();

        $this->assertNull(AuditChainRecord::where('scope', AuditChainRecord::Central)->sole()->broken_entry_id);
    }

    public function test_les_faits_de_plus_de_quatre_vingt_dix_jours_sont_purges(): void
    {
        SecurityEvent::create(['type' => SecurityEvent::RateLimited, 'created_at' => now()->subDays(91)]);
        SecurityEvent::create(['type' => SecurityEvent::RateLimited, 'created_at' => now()->subDays(89)]);

        $this->assertSame(1, SecurityJournal::purge());
        $this->assertSame(1, SecurityEvent::count());
    }

    public function test_l_ecran_liste_les_blocages_recents(): void
    {
        SecurityEvent::create(['type' => SecurityEvent::LoginLockout, 'subject' => 'cible@convive.test', 'ip' => '203.0.113.9', 'created_at' => now()]);

        $this->actingAs($this->founder)
            ->get(route('console.security'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.lockouts', 1)
                ->where('events.0.subject', 'cible@convive.test'),
            );
    }

    public function test_seuls_les_fondateurs_ouvrent_l_ecran(): void
    {
        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)->get(route('console.security'))->assertForbidden();

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)->get(route('console.security'))->assertNotFound();
    }
}
