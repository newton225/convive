<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Fortify;

/**
 * Codes de secours de la double authentification (SECURITY.md M5) : les codes deja enregistres,
 * lisibles, sont remplaces par leur empreinte SHA-256. Ils continuent de fonctionner, mais ne
 * peuvent plus etre reaffiches : qui ne les a pas notes en genere de nouveaux.
 */
return new class extends Migration
{
    public function up(): void
    {
        $encrypter = Fortify::currentEncrypter();

        DB::table('users')
            ->whereNotNull('two_factor_recovery_codes')
            ->orderBy('id')
            ->each(function (object $user) use ($encrypter) {
                try {
                    $codes = json_decode((string) $encrypter->decrypt($user->two_factor_recovery_codes), true);
                } catch (DecryptException) {
                    // Illisible avec la cle actuelle : ces codes ne valaient deja plus rien.
                    return;
                }

                if (! is_array($codes)) {
                    return;
                }

                $fingerprints = array_map(
                    fn (mixed $code) => strlen((string) $code) === 64 && ctype_xdigit((string) $code)
                        ? (string) $code
                        : hash('sha256', (string) $code),
                    array_values($codes),
                );

                DB::table('users')->where('id', $user->id)->update([
                    'two_factor_recovery_codes' => $encrypter->encrypt((string) json_encode($fingerprints)),
                ]);
            });
    }

    /**
     * Sans retour possible : une empreinte ne redonne pas le code.
     */
    public function down(): void
    {
        //
    }
};
