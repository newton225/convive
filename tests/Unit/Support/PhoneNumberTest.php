<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Numeros de telephone. Un numero se ramene a une forme unique (E.164, `+` et l'indicatif du pays)
 * quelle que soit son ecriture : sans cela, le meme telephone passait pour deux numeros et l'attente
 * imposee apres des reservations expirees se contournait en ajoutant ou retirant l'indicatif
 * (SECURITY.md C3).
 *
 * Invites : tous les pays, un numero sans indicatif etant lu comme ivoirien (decision du
 * proprietaire du projet, 2026-09-29, revenant sur « Cote d'Ivoire seule »). Comptes de versement :
 * Cote d'Ivoire seule, le prefixe designant le reseau Mobile Money.
 */
class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function sameIvorianNumberWrittenDifferently(): array
    {
        return [
            'forme internationale espacee' => ['+225 07 07 12 34 56'],
            'forme internationale collee' => ['+2250707123456'],
            'sans indicatif' => ['07 07 12 34 56'],
            'sans indicatif ni espace' => ['0707123456'],
            'prefixe international 00' => ['00225 07 07 12 34 56'],
            'indicatif entre parentheses' => ['(+225) 07 07 12 34 56'],
            'tirets et points' => ['+225-07.07-12.34-56'],
            'indicatif sans plus' => ['225 07 07 12 34 56'],
        ];
    }

    #[DataProvider('sameIvorianNumberWrittenDifferently')]
    public function test_toutes_les_ecritures_d_un_numero_ivoirien_ramenent_a_la_meme_forme(string $input): void
    {
        $this->assertSame('+2250707123456', PhoneNumber::normalize($input));
        $this->assertSame('+2250707123456', PhoneNumber::normalizeIvorian($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function foreignNumbers(): array
    {
        return [
            'France avec +' => ['+33 6 12 34 56 78', '+33612345678'],
            'France avec 00' => ['0033 6 12 34 56 78', '+33612345678'],
            'Senegal' => ['+221 77 123 45 67', '+221771234567'],
            'Etats-Unis' => ['+1 (415) 555-2671', '+14155552671'],
        ];
    }

    #[DataProvider('foreignNumbers')]
    public function test_un_numero_etranger_avec_son_indicatif_est_accepte_pour_un_invite(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    #[DataProvider('foreignNumbers')]
    public function test_un_numero_etranger_est_refuse_pour_un_compte_de_versement(string $input): void
    {
        $this->assertNull(PhoneNumber::normalizeIvorian($input));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalid(): array
    {
        return [
            'trop court' => ['07 07 12 34 5'],
            'trop long' => ['07 07 12 34 56 7'],
            'ancien format a 8 chiffres' => ['07 12 34 56'],
            // Sans indicatif, le numero est lu comme ivoirien : un 06 francais doit porter son +33.
            'numero etranger sans indicatif' => ['06 12 34 56 78'],
            'pas un numero' => ['pas un numero'],
            'vide' => [''],
        ];
    }

    #[DataProvider('invalid')]
    public function test_un_numero_invalide_est_refuse(string $input): void
    {
        $this->assertNull(PhoneNumber::normalize($input));
        $this->assertNull(PhoneNumber::normalizeIvorian($input));
    }

    public function test_le_numero_s_affiche_par_paires(): void
    {
        $this->assertSame('+225 07 07 12 34 56', PhoneNumber::format('+2250707123456'));
    }

    public function test_le_prefixe_designe_l_operateur(): void
    {
        $this->assertSame('07', PhoneNumber::prefix('+2250707123456'));
        $this->assertSame('05', PhoneNumber::prefix('+2250505112233'));
    }

    public function test_deux_ecritures_du_meme_numero_designent_le_meme_telephone(): void
    {
        $this->assertTrue(PhoneNumber::same('+225 07 07 12 34 56', '07 07 12 34 56'));
        $this->assertTrue(PhoneNumber::same('+33 6 12 34 56 78', '0033612345678'));
        $this->assertFalse(PhoneNumber::same('+225 07 07 12 34 56', '+225 05 07 12 34 56'));
    }
}
