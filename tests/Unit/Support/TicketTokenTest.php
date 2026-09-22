<?php

namespace Tests\Unit\Support;

use App\Support\TicketToken;
use Tests\TestCase;

/**
 * Signature Ed25519 du jeton QR (README 2.8, SECURITY.md C2), etape 7 de « Ordre de
 * construction ». Le scan devant fonctionner hors ligne, seule la cle publique verifie ; ces
 * tests eprouvent ce contrat independamment de toute base de donnees.
 */
class TicketTokenTest extends TestCase
{
    /**
     * @return array{public: string, secret: string}
     */
    private function keyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();

        return [
            'public' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
            'secret' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
        ];
    }

    public function test_un_jeton_signe_se_verifie_avec_la_cle_publique_correspondante(): void
    {
        $keys = $this->keyPair();
        $token = TicketToken::sign(['registration_id' => 42], $keys['secret']);

        $payload = TicketToken::verify($token, $keys['public']);

        $this->assertSame(['registration_id' => 42], $payload);
    }

    public function test_refuse_un_jeton_signe_par_une_autre_cle(): void
    {
        $keys = $this->keyPair();
        $otherKeys = $this->keyPair();
        $token = TicketToken::sign(['registration_id' => 42], $keys['secret']);

        $this->assertNull(TicketToken::verify($token, $otherKeys['public']));
    }

    public function test_refuse_une_charge_utile_modifiee_apres_signature(): void
    {
        $keys = $this->keyPair();
        $token = TicketToken::sign(['registration_id' => 42], $keys['secret']);
        [$payload, $signature] = explode('.', $token);

        $tamperedPayload = TicketToken::sign(['registration_id' => 99], $keys['secret']);
        [$forgedPayload] = explode('.', $tamperedPayload);

        $this->assertNull(TicketToken::verify("{$forgedPayload}.{$signature}", $keys['public']));
    }

    public function test_refuse_un_jeton_malforme(): void
    {
        $keys = $this->keyPair();

        $this->assertNull(TicketToken::verify('pas-un-jeton-signe', $keys['public']));
        $this->assertNull(TicketToken::verify('', $keys['public']));
        $this->assertNull(TicketToken::verify('a.b.c', $keys['public']));
    }
}
