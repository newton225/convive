<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Ouvrir ou fermer l'interrupteur « une seule session par compte ».
 */
class UpdateSingleSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('console.area', ConsoleArea::Security->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'single_session' => ['required', 'boolean'],
        ];
    }
}
