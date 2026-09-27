<?php

namespace Tests\Feature\Events;

use App\Enums\RegistrationStatus;
use App\Exports\RegistrationsExport;
use App\Models\Registration;
use App\Models\Unit;
use App\Support\SpreadsheetSafe;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SECURITY.md M2 : une valeur qui commence par `=`, `+`, `-`, `@`, une tabulation ou un retour
 * chariot est neutralisee a l'export, sinon elle s'execute comme formule a l'ouverture du
 * fichier par l'organisateur.
 */
class RegistrationExportFormulaTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function hostileValues(): array
    {
        return [
            'egal' => ['=HYPERLINK("http://evil.example","clic")'],
            'plus' => ['+cmd|\' /C calc\'!A0'],
            'moins' => ['-2+3+cmd|\' /C calc\'!A0'],
            'arobase' => ['@SUM(1+1)*cmd|\' /C calc\'!A0'],
            'tabulation' => ["\t=1+1"],
            'retour_chariot' => ["\r=1+1"],
        ];
    }

    #[DataProvider('hostileValues')]
    public function test_une_valeur_hostile_est_prefixee_d_une_apostrophe(string $value): void
    {
        $this->assertSame("'".$value, SpreadsheetSafe::cell($value));
    }

    public function test_une_valeur_ordinaire_reste_inchangee(): void
    {
        $this->assertSame('Aya Kouassi', SpreadsheetSafe::cell('Aya Kouassi'));
        $this->assertSame('', SpreadsheetSafe::cell(''));
        $this->assertNull(SpreadsheetSafe::cell(null));
        $this->assertSame(3, SpreadsheetSafe::cell(3));
    }

    public function test_l_export_neutralise_le_nom_l_email_l_unite_et_le_motif_mais_pas_le_telephone(): void
    {
        $registration = new Registration([
            'name' => '=HYPERLINK("http://evil.example","clic")',
            'phone' => '+225 07 00 00 00 00',
            'email' => '=cmd@example.com',
            'status' => RegistrationStatus::Cancelled,
            'party_size' => 1,
            'amount_due' => 15000,
            'cancellation_reason' => '@SUM(1+1)',
        ]);
        $registration->setRelation('unit', new Unit(['name' => '+ELIAKIM']));
        $registration->setRelation('tableAssignment', null);

        $row = (new RegistrationsExport($this->createMock(Builder::class)))->map($registration);

        $this->assertSame("'=HYPERLINK(\"http://evil.example\",\"clic\")", $row[0]);
        // Un numero legitime commence souvent par « + » : le prefixer le defigurerait, et il ne
        // peut de toute facon contenir aucun appel de fonction (voir `SpreadsheetSafe`).
        $this->assertSame('+225 07 00 00 00 00', $row[1]);
        $this->assertSame("'=cmd@example.com", $row[2]);
        $this->assertSame("'+ELIAKIM", $row[3]);
        $this->assertSame("'@SUM(1+1)", $row[8]);
    }
}
