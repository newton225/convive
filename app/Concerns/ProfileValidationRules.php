<?php

namespace App\Concerns;

use App\Models\User;
use App\Rules\GuestPhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'phone' => $this->phoneRules(),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user phone numbers.
     *
     * Sert a router les alertes WhatsApp (changement de compte de versement, CLAUDE.md). Facultatif
     * dans le profil ; demande a l'inscription (prototype Convive.dc.html, decision du 2026-09-27).
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(bool $required = false): array
    {
        // Meme regle que le telephone d'un invite : tout pays, un numero sans indicatif etant lu comme
        // ivoirien. Le numero est ensuite enregistre sous sa forme unique (`PhoneNumber::normalize`),
        // celle qu'attend WhatsApp pour les alertes des membres.
        return [$required ? 'required' : 'nullable', 'string', 'max:32', new GuestPhoneNumber];
    }
}
