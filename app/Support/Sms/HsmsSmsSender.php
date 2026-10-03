<?php

namespace App\Support\Sms;

use App\Contracts\SmsSender;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * L'envoi de SMS par HSMS, agregateur d'Abidjan (choix du proprietaire du projet, 2026-10-03), par
 * le client HTTP de Laravel. Le nom d'expediteur se regle sur l'application, dans l'espace HSMS,
 * pas dans la requete.
 *
 * Documentation : https://hsms.ci/doc-api/
 */
class HsmsSmsSender implements SmsSender
{
    public const TokenCacheKey = 'sms:hsms:token';

    private const BaseUrl = 'https://hsms.ci/api';

    /**
     * HSMS ne dit pas combien de temps son jeton vaut : garde une heure, et redemande des qu'il est
     * refuse.
     */
    private const TokenSeconds = 3600;

    public function __construct(
        private readonly string $email,
        private readonly string $password,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    /**
     * @throws RequestException|RuntimeException quand HSMS refuse : l'envoi echoue au lieu de
     *                                           laisser croire le code parti.
     */
    public function send(string $to, string $message): void
    {
        $response = $this->post($to, $message, $this->token());

        if ($response->status() === 401) {
            Cache::forget(self::TokenCacheKey);
            $response = $this->post($to, $message, $this->token());
        }

        $response->throw();

        // HSMS peut repondre 200 en refusant l'envoi (credit epuise, numero invalide...).
        if ($response->json('success') !== true) {
            throw new RuntimeException('HSMS : '.($response->json('message') ?? 'envoi refuse'));
        }
    }

    public function delivers(): bool
    {
        return true;
    }

    private function post(string $to, string $message, string $token): Response
    {
        return Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->post(self::BaseUrl.'/envoi-sms', [
                'clientid' => $this->clientId,
                'clientsecret' => $this->clientSecret,
                // L'indicatif sans le « + » : `2250707123456`.
                'telephone' => ltrim($to, '+'),
                'message' => $message,
            ]);
    }

    private function token(): string
    {
        $cached = Cache::get(self::TokenCacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->post(self::BaseUrl.'/token/', ['email' => $this->email, 'password' => $this->password])
            ->throw();

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('HSMS : '.($response->json('message') ?? 'jeton refuse'));
        }

        Cache::put(self::TokenCacheKey, $token, self::TokenSeconds);

        return $token;
    }
}
