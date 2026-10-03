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
     * Les variables, chacune sur une seule ligne : Meta refuse un message dont une variable porte
     * un retour a la ligne, une tabulation ou plus de quatre espaces de suite (un motif saisi dans
     * une zone de texte en porte souvent).
     *
     * @var array<int, string>
     */
    public array $parameters;

    /**
     * @param  array<int, string>  $parameters  dans l'ordre des variables du modele : {{1}}, {{2}}...
     * @param  bool  $copyCodeButton  modele d'authentification : Meta attend le code une seconde fois,
     *                                pour son bouton « copier le code »
     */
    public function __construct(
        public string $key,
        array $parameters,
        public bool $copyCodeButton = false,
    ) {
        $this->parameters = array_map(
            fn (string $value) => trim((string) preg_replace('/\s+/u', ' ', $value)),
            array_values($parameters),
        );
    }

    /**
     * An authentication template, whose text is set by the provider and carries the code alone.
     */
    public static function authentication(string $key, string $code): self
    {
        return new self($key, [$code], copyCodeButton: true);
    }

    /**
     * Get the provider's own identifier of this template, or null when none was declared.
     */
    public function configured(): ?string
    {
        $value = config("services.whatsapp.templates.{$this->key}");

        return is_string($value) && $value !== '' ? $value : null;
    }
}
