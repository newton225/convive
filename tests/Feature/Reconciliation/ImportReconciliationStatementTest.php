<?php

namespace Tests\Feature\Reconciliation;

use App\Actions\Reconciliation\ImportReconciliationStatement;
use App\Actions\Tenants\CreateTenant;
use App\Enums\ReconciliationOutcome;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\StatementImport;
use App\Models\StatementLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Import CSV d'un releve Mobile Money ou bancaire (README 2.10, ecran 19), etape 9 de « Ordre
 * de construction ».
 *
 * Un releve est un document financier : une ligne mal formee rejette l'import entier plutot
 * que d'en importer une partie en silence.
 */
class ImportReconciliationStatementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Event $event;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->agent, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    private function csv(string $content, string $name = 'releve.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function proofFor(string $name, string $reference, int $amount, ?Event $event = null): PaymentProof
    {
        $registration = Registration::factory()->proofSubmitted()->create([
            'event_id' => ($event ?? $this->event)->id,
            'name' => $name,
            'amount_due' => $amount,
        ]);

        return PaymentProof::factory()->withReference($reference)->create([
            'registration_id' => $registration->id,
            'amount_declared' => $amount,
        ]);
    }

    private function import(string $content, ?Event $event = null, string $name = 'releve.csv'): StatementImport
    {
        return $this->tenant->asCurrent(
            fn () => app(ImportReconciliationStatement::class)->handle($event ?? $this->event, $this->csv($content, $name), $this->agent),
        );
    }

    public function test_produit_les_quatre_issues_sur_un_jeu_de_preuves_construit_a_la_main(): void
    {
        $this->tenant->asCurrent(function () {
            $this->proofFor('Aya Kouassi', 'WV0001', 15000);
            $this->proofFor('Kofi Diallo', 'WV0002', 15000);
            $this->proofFor('Marie Traore', 'WV0003', 20000);
        });

        $import = $this->import(implode("\n", [
            'date,reference,emetteur,montant',
            '2026-09-20,WV0001,Aya Kouassi,15000',      // rapprochee
            '2026-09-20,WV0002,Kofi Diallo,9000',       // montant divergent
            '2026-09-20,WX9999,Marie Traore,20000',     // nom approchant (reference inconnue)
            '2026-09-20,WZ8888,Inconnu Total,7000',     // sans inscription
        ]));

        $outcomes = $this->tenant->asCurrent(
            fn () => StatementLine::where('statement_import_id', $import->id)->orderBy('line_number')->pluck('outcome')->all(),
        );

        $this->assertSame([
            ReconciliationOutcome::Matched,
            ReconciliationOutcome::AmountMismatch,
            ReconciliationOutcome::ApproximateName,
            ReconciliationOutcome::NoRegistration,
        ], $outcomes);
    }

    public function test_enregistre_l_import_et_ses_lignes(): void
    {
        $import = $this->import("date,reference,emetteur,montant\n2026-09-20,WV0001,Aya Kouassi,15000\n2026-09-21,WV0002,Kofi Diallo,10000");

        $this->tenant->asCurrent(function () use ($import) {
            $this->assertSame($this->event->id, $import->event_id);
            $this->assertSame($this->agent->id, $import->imported_by_user_id);
            $this->assertSame('releve.csv', $import->original_filename);
            $this->assertSame(2, $import->row_count);

            $line = StatementLine::where('statement_import_id', $import->id)->orderBy('line_number')->first();

            $this->assertSame(1, $line->line_number);
            $this->assertSame('2026-09-20', $line->occurred_on->toDateString());
            $this->assertSame('WV0001', $line->reference);
            $this->assertSame('Aya Kouassi', $line->issuer);
            $this->assertSame(15000, $line->amount);
        });
    }

    public function test_relie_la_ligne_rapprochee_a_l_inscription_et_a_la_preuve(): void
    {
        $proof = $this->tenant->asCurrent(fn () => $this->proofFor('Aya Kouassi', 'WV0001', 15000));

        $import = $this->import("date,reference,emetteur,montant\n2026-09-20,WV0001,Aya Kouassi,15000");

        $line = $this->tenant->asCurrent(fn () => StatementLine::where('statement_import_id', $import->id)->firstOrFail());

        $this->assertSame($proof->id, $line->matched_payment_proof_id);
        $this->assertSame($proof->registration_id, $line->matched_registration_id);
    }

    public function test_les_en_tetes_sont_reconnus_sans_casse_ni_accents_et_avec_le_point_virgule(): void
    {
        $import = $this->import("Date;Référence;Émetteur;Montant\n20/09/2026;WV0001;Aya Kouassi;15 000");

        $line = $this->tenant->asCurrent(fn () => StatementLine::where('statement_import_id', $import->id)->firstOrFail());

        $this->assertSame('2026-09-20', $line->occurred_on->toDateString());
        $this->assertSame(15000, $line->amount);
    }

    public function test_une_colonne_obligatoire_absente_rejette_l_import(): void
    {
        try {
            $this->import("date,reference,montant\n2026-09-20,WV0001,15000");
            $this->fail('Un releve sans colonne emetteur aurait du etre rejete.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertSame(0, $this->tenant->asCurrent(fn () => StatementImport::count()));
    }

    public function test_une_ligne_mal_formee_rejette_tout_l_import_avec_son_numero(): void
    {
        try {
            $this->import(implode("\n", [
                'date,reference,emetteur,montant',
                '2026-09-20,WV0001,Aya Kouassi,15000',
                '2026-09-20,WV0002,Kofi Diallo,pas-un-montant',
            ]));
            $this->fail('Une ligne au montant illisible aurait du rejeter le releve.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('2', $exception->errors()['file'][0]);
        }

        $this->tenant->asCurrent(function () {
            $this->assertSame(0, StatementImport::count());
            $this->assertSame(0, StatementLine::count());
        });
    }

    public function test_une_date_illisible_rejette_l_import(): void
    {
        $this->expectException(ValidationException::class);

        $this->import("date,reference,emetteur,montant\nhier,WV0001,Aya Kouassi,15000");
    }

    public function test_un_fichier_sans_ligne_de_donnees_est_rejete(): void
    {
        $this->expectException(ValidationException::class);

        $this->import('date,reference,emetteur,montant');
    }

    public function test_une_reference_repetee_dans_le_meme_releve_ne_consomme_qu_une_preuve(): void
    {
        $this->tenant->asCurrent(fn () => $this->proofFor('Aya Kouassi', 'WV0001', 15000));

        $import = $this->import(implode("\n", [
            'date,reference,emetteur,montant',
            '2026-09-20,WV0001,Aya Kouassi,15000',
            '2026-09-20,WV0001,Aya Kouassi,15000',
        ]));

        $outcomes = $this->tenant->asCurrent(
            fn () => StatementLine::where('statement_import_id', $import->id)->orderBy('line_number')->pluck('outcome')->all(),
        );

        $this->assertSame([ReconciliationOutcome::Matched, ReconciliationOutcome::NoRegistration], $outcomes);
    }

    public function test_un_reimport_identique_est_rejete_sans_doublon(): void
    {
        $content = "date,reference,emetteur,montant\n2026-09-20,WV0001,Aya Kouassi,15000";

        $this->import($content);

        try {
            $this->import($content, name: 'renomme.csv');
            $this->fail('Le meme contenu aurait du etre refuse une seconde fois.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->tenant->asCurrent(function () {
            $this->assertSame(1, StatementImport::count());
            $this->assertSame(1, StatementLine::count());
        });
    }

    public function test_le_meme_contenu_reste_importable_sur_un_autre_evenement(): void
    {
        $content = "date,reference,emetteur,montant\n2026-09-20,WV0001,Aya Kouassi,15000";
        $otherEvent = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());

        $this->import($content);
        $this->import($content, $otherEvent);

        $this->assertSame(2, $this->tenant->asCurrent(fn () => StatementImport::count()));
    }

    public function test_une_preuve_d_un_autre_evenement_n_est_jamais_rapprochee(): void
    {
        $otherEvent = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
        $this->tenant->asCurrent(fn () => $this->proofFor('Aya Kouassi', 'WV0001', 15000, $otherEvent));

        $import = $this->import("date,reference,emetteur,montant\n2026-09-20,WV0001,Aya Kouassi,15000");

        $line = $this->tenant->asCurrent(fn () => StatementLine::where('statement_import_id', $import->id)->firstOrFail());

        $this->assertSame(ReconciliationOutcome::NoRegistration, $line->outcome);
        $this->assertNull($line->matched_payment_proof_id);
    }

    public function test_l_import_est_journalise(): void
    {
        $import = $this->import("date,reference,emetteur,montant\n2026-09-20,WV0001,Aya Kouassi,15000");

        $activity = $this->tenant->asCurrent(
            fn () => Activity::where('description', 'reconciliation.imported')->latest('id')->first(),
        );

        $this->assertNotNull($activity);
        $this->assertSame($import->id, $activity->subject_id);
        $this->assertSame($this->agent->id, $activity->causer_id);
    }
}
