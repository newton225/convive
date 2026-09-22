<?php

namespace Tests\Unit\Support;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Verifie que `maatwebsite/excel` produit reellement un classeur dans cet environnement, etape 9.
 */
class ExcelExportTest extends TestCase
{
    public function test_un_export_trivial_telecharge_un_fichier_xlsx(): void
    {
        $export = new class implements FromArray
        {
            public function array(): array
            {
                return [['a', 'b'], [1, 2]];
            }
        };

        $response = Excel::download($export, 'smoke-test.xlsx', ExcelFormat::XLSX);

        $this->assertNotEmpty($response->getFile()->getSize());
    }
}
