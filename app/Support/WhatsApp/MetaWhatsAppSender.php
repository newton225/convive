<?php

namespace App\Support\WhatsApp;

use App\Contracts\WhatsAppSender;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * L'envoi WhatsApp directement chez Meta (API Cloud), pret pour le jour ou le compte Meta du
 * proprietaire du projet sera valide : il suffit alors de passer `WHATSAPP_DRIVER` a `meta`.
 *
 * Un modele declare part par son nom chez Meta, dans la langue reglee, avec ses parametres dans
 * l'ordre ; sinon le texte part tel quel, ce que WhatsApp n'accepte que dans les 24 heures qui
 * suivent un message de la personne.
 */
class MetaWhatsAppSender implements WhatsAppSender
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $phoneNumberId,
        private readonly string $apiVersion,
        private readonly string $templateLanguage,
    ) {}

    /**
     * @throws RequestException quand Meta refuse : la tache d'envoi echoue et se relance.
     */
    public function send(string $to, string $message, ?WhatsAppTemplate $template = null): void
    {
        $name = $template?->configured();

        $payload = [
            'messaging_product' => 'whatsapp',
            // Meta attend le numero sans le « + ».
            'to' => ltrim($to, '+'),
            ...($name !== null
                ? [
                    'type' => 'template',
                    'template' => [
                        'name' => $name,
                        'language' => ['code' => $this->templateLanguage],
                        'components' => self::components($template),
                    ],
                ]
                : ['type' => 'text', 'text' => ['body' => $message]]),
        ];

        Http::withToken($this->accessToken)
            ->timeout(15)
            ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload)
            ->throw();
    }

    public function delivers(): bool
    {
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function components(WhatsAppTemplate $template): array
    {
        $parameters = array_map(fn (string $value) => ['type' => 'text', 'text' => $value], $template->parameters);
        $components = [['type' => 'body', 'parameters' => $parameters]];

        // Le bouton « copier le code » d'un modele d'authentification se declare chez Meta comme un
        // bouton de lien, et attend le code en parametre, en plus du corps du message.
        if ($template->copyCodeButton) {
            $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [$parameters[0]]];
        }

        return $components;
    }
}
