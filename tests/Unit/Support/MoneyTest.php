<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Formatage des montants dans les PDF (exports, rapports). Reproduit le bogue du 2026-09-27 :
 * l'export PDF de la base d'inscrits tombait en erreur 500 (« Class NumberFormatter not found »)
 * sur un PHP sans l'extension `intl`. Le format ne doit dependre d'aucune extension facultative.
 */
class MoneyTest extends TestCase
{
    public function test_en_francais_les_milliers_sont_separes_par_une_espace_fine_insecable(): void
    {
        App::setLocale('fr');

        $this->assertSame("1\u{202F}250\u{202F}000 F CFA", Money::format(1250000));
    }

    public function test_en_anglais_les_milliers_sont_separes_par_une_virgule(): void
    {
        App::setLocale('en');

        $this->assertSame('1,250,000 F CFA', Money::format(1250000));
    }

    public function test_un_petit_montant_n_a_pas_de_separateur(): void
    {
        App::setLocale('fr');

        $this->assertSame('500 F CFA', Money::format(500));
    }
}
