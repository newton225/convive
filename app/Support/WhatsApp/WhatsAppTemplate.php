<?php

namespace App\Support\WhatsApp;

/**
 * Le modele de message WhatsApp qui correspond a un envoi, et ses variables dans l'ordre.
 *
 * WhatsApp n'autorise un message libre que dans les 24 heures qui suivent un message de la
 * personne ; au-dela, et donc pour presque tous les envois de Convive, il faut un modele approuve.
 * La cle (`proof_reminder`...) est celle de l'application ; le modele reel qui lui correspond chez
 * le service (identifiant Twilio, nom chez Meta) se declare par reglage, dans
 * `config('services.whatsapp.templates')`. Sans modele declare, le texte part tel quel.
 */
final readonly class WhatsAppTemplate
{
    /**
     * @param  array<int, string>  $parameters  dans l'ordre des variables du modele : {{1}}, {{2}}...
     */
    public function __construct(
        public string $key,
        public array $parameters,
    ) {}

    /**
     * Get the provider's own identifier of this template, or null when none was declared.
     */
    public function configured(): ?string
    {
        $value = config("services.whatsapp.templates.{$this->key}");

        return is_string($value) && $value !== '' ? $value : null;
    }
}
