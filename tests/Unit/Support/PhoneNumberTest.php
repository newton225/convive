<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Numeros de telephone ivoiriens (decision du proprietaire du projet, 2026-09-29 : Cote d'Ivoire
 * seule). Un numero se ramene a une forme unique, `+225` suivi de ses 10 chiffres, quelle que soit
 * son ecriture : sans cela, le meme telephone passait pour deux numeros et l'attente imposee apres
 * des reservations expirees se contournait en ajoutant ou retirant l'indicatif (SECURITY.md C3).
 */
class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function sameNumberWrittenDifferently(): array
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

    #[DataProvider('sameNumberWrittenDifferently')]
    public function test_toutes_les_ecritures_d_un_numero_ramenent_a_la_meme_forme(string $input): void
    {
        $this->assertSame('+2250707123456', PhoneNumber::normalize($input));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function notIvorian(): array
    {
        return [
            'trop court' => ['07 07 12 34 5'],
            'trop long' => ['07 07 12 34 56 7'],
            'ancien format a 8 chiffres' => ['07 12 34 56'],
            'autre pays' => ['+221 77 123 45 67'],
            'pas un numero' => ['pas un numero'],
            'vide' => [''],
        ];
    }

    #[DataProvider('notIvorian')]
    public function test_un_numero_qui_n_est_pas_ivoirien_est_refuse(string $input): void
    {
        $this->assertNull(PhoneNumber::normalize($input));
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
        $this->assertFalse(PhoneNumber::same('+225 07 07 12 34 56', '+225 05 07 12 34 56'));
    }
}
