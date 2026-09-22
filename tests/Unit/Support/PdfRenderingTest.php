<?php

namespace Tests\Unit\Support;

use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

/**
 * Verifie que `spatie/laravel-pdf` produit reellement un PDF dans cet environnement, etape 9.
 * Pilote DomPDF (pur PHP, `LARAVEL_PDF_DRIVER=dompdf`) plutot que Browsershot : aucun binaire
 * Chrome/Node a installer sur ce poste ou en production, seul le pilote change si un besoin de
 * rendu plus fidele au CSS moderne apparaissait plus tard.
 */
class PdfRenderingTest extends TestCase
{
    public function test_un_rendu_html_trivial_produit_un_fichier_pdf(): void
    {
        $base64 = Pdf::html('<h1>Test</h1>')->base64();

        $content = base64_decode($base64);

        $this->assertStringStartsWith('%PDF', $content);
    }
}
