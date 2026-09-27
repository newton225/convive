<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScanPinRequest extends FormRequest
{
    /**
     * Exactement quatre chiffres, saisis deux fois : le code se tape au pouce devant une file
     * d'attente, il doit etre court, et une faute de frappe a la creation enfermerait l'agent.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pin' => ['required', 'string', 'regex:/^\d{4}$/', 'confirmed'],
        ];
    }
}
