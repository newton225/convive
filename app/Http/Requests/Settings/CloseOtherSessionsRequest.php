<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloseOtherSessionsRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Le mot de passe est redemande : `Auth::logoutOtherDevices()` en a besoin, et une session
     * volee ne doit pas pouvoir fermer celle du titulaire legitime.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
