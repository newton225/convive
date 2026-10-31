<?php

namespace Tests\Unit;

use App\Rules\IvorianPhoneNumber;
use App\Rules\PersonName;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les champs du formulaire d'inscription (decision du 2026-10-31) : un nom sans chiffre ni symbole, un
 * telephone ivoirien a dix chiffres.
 */
class GuestInputRulesTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function validNames(): array
    {
        return [
            'simple' => ['Aya Kouame'],
            'accents' => ['Chantal Kouamé-N’Guessan'],
            'apostrophe' => ["Jean N'Dri"],
            'tiret' => ['Marie-Claire Kone'],
            'trois mots' => ['Jean Marc Kouame'],
            'deux lettres par mot' => ['Al Ba'],
            'lettre doublee' => ['Aaron Kooné'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidNames(): array
    {
        return [
            'chiffre' => ['Aya2 Kouame'],
            'symbole' => ['Aya@Kouame'],
            'balise' => ['<b>Aya</b>'],
            'que des chiffres' => ['12345'],
            'commence par un tiret' => ['-Aya'],
            'point' => ['A. Kouame'],
            'tirets de suite' => ['Aya----Kouame'],
            'espaces de suite' => ['Aya    Kouame'],
            'separateurs differents de suite' => ["Aya -'Kouame"],
            'separateur a la fin' => ['Aya-'],
            'guillemets et suites' => ['dfdfdf fgfgf---- """"" ddfdfdfd'],
            'un seul mot' => ['Kouame'],
            'un seul mot compose' => ['Marie-Claire'],
            'un mot d une lettre' => ['A Kouame'],
            'lettre repetee trois fois' => ['Aaa Kouame'],
            'lettre repetee en majuscules' => ['Aya KKK'],
            'lettres accentuees repetees' => ['Aya Kouèèèè'],
        ];
    }

    #[DataProvider('validNames')]
    public function test_un_nom_de_lettres_est_accepte(string $name): void
    {
        $this->assertTrue(Validator::make(['name' => $name], ['name' => [new PersonName]])->passes());
    }

    #[DataProvider('invalidNames')]
    public function test_un_nom_avec_chiffre_ou_symbole_est_refuse(string $name): void
    {
        $this->assertTrue(Validator::make(['name' => $name], ['name' => [new PersonName]])->fails());
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function phones(): array
    {
        return [
            'forme complete' => ['+2250707123456', true],
            'dix chiffres' => ['0707123456', true],
            'avec espaces' => ['07 07 12 34 56', true],
            'ancien format a huit chiffres' => ['07123456', false],
            'neuf chiffres' => ['070712345', false],
            'onze chiffres' => ['07071234567', false],
            'prefixe inconnu' => ['0907123456', false],
            'numero francais' => ['+33612345678', false],
            'lettres' => ['07abcdefgh', false],
        ];
    }

    #[DataProvider('phones')]
    public function test_le_telephone_doit_etre_ivoirien_a_dix_chiffres(string $phone, bool $accepted): void
    {
        $validator = Validator::make(['phone' => $phone], ['phone' => [new IvorianPhoneNumber]]);

        $this->assertSame($accepted, $validator->passes());
    }
}
