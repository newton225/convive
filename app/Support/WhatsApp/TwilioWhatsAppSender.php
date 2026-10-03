<?php

namespace App\Support\WhatsApp;

use App\Contracts\WhatsAppSender;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * L'envoi WhatsApp par Twilio (decision du proprietaire du projet, 2026-10-03), par son API REST et
 * le client HTTP de Laravel : aucun paquet a installer pour un seul appel.
 *
 * Un modele declare part par son identifiant de contenu Twilio (`ContentSid`, « HX... ») et ses
 * variables numerotees ; sinon, le texte part tel quel, ce que WhatsApp n'accepte que dans les 24
 * heures qui suivent un message de la personne (ou dans le bac a sable de Twilio).
 */
class TwilioWhatsAppSender implements WhatsAppSender
{
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $from,
    ) {}

    /**
     * @throws RequestException quand Twilio refuse : la tache d'envoi echoue et se relance.
     */
    public function send(string $to, string $message, ?WhatsAppTemplate $template = null): void
    {
        $content = $template?->configured();

        $payload = [
            'From' => 'whatsapp:'.$this->from,
            'To' => 'whatsapp:'.$to,
            ...($content !== null
                ? [
                    'ContentSid' => $content,
                    'ContentVariables' => (string) json_encode(self::variables($template->parameters)),
                ]
                : ['Body' => $message]),
        ];

        Http::asForm()
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", $payload)
            ->throw();
    }

    public function delivers(): bool
    {
        return true;
    }

    /**
     * @param  array<int, string>  $parameters
     * @return array<int, string> numerotees a partir de 1, comme dans le modele : encodees, elles
     *                            donnent l'objet {"1": ..., "2": ...} qu'attend Twilio
     */
    private static function variables(array $parameters): array
    {
        $variables = [];

        foreach (array_values($parameters) as $index => $value) {
            $variables[$index + 1] = $value;
        }

        return $variables;
    }
}
