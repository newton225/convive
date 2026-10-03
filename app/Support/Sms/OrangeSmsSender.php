<?php

namespace App\Support\Sms;

use App\Contracts\SmsSender;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * L'envoi de SMS par l'API SMS d'Orange Cote d'Ivoire (decision du proprietaire du projet,
 * 2026-10-03), par le client HTTP de Laravel : aucun paquet pour deux appels. Orange livre aussi
 * les numeros des autres operateurs ivoiriens.
 *
 * Documentation : https://developer.orange.com/apis/sms/getting-started
 */
class OrangeSmsSender implements SmsSender
{
    public const TokenCacheKey = 'sms:orange:token';

    /**
     * L'adresse d'envoi qu'Orange attribue a la Cote d'Ivoire, la meme pour tous ses clients.
     */
    private const SenderAddress = 'tel:+2250000';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly ?string $senderName = null,
    ) {}

    /**
     * @throws RequestException quand Orange refuse (credit epuise, numero invalide...) : l'envoi
     *                          echoue au lieu de laisser croire le code parti.
     */
    public function send(string $to, string $message): void
    {
        Http::withToken($this->token())
            ->timeout(15)
            ->post('https://api.orange.com/smsmessaging/v1/outbound/'.rawurlencode(self::SenderAddress).'/requests', [
                'outboundSMSMessageRequest' => [
                    'address' => 'tel:'.$to,
                    'senderAddress' => self::SenderAddress,
                    // Le nom d'expediteur (« Convive ») n'est accepte qu'une fois accorde par Orange.
                    ...(filled($this->senderName) ? ['senderName' => $this->senderName] : []),
                    'outboundSMSTextMessage' => ['message' => $message],
                ],
            ])
            ->throw();
    }

    public function delivers(): bool
    {
        return true;
    }

    /**
     * Le jeton d'acces vaut une heure : garde en cache un peu moins longtemps, pour ne jamais
     * envoyer avec un jeton qui expire en route.
     */
    private function token(): string
    {
        $cached = Cache::get(self::TokenCacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->timeout(15)
            ->post('https://api.orange.com/oauth/v3/token', ['grant_type' => 'client_credentials'])
            ->throw();

        $token = (string) $response->json('access_token');
        Cache::put(self::TokenCacheKey, $token, max(60, (int) $response->json('expires_in', 3600) - 300));

        return $token;
    }
}
