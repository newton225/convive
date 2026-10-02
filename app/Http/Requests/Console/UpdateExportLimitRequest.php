<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Regler la limite d'exports par heure (SECURITY.md M3). Toujours une limite : un champ vide ou
 * zero est refuse, la protection ne se retire pas depuis un ecran.
 */
class UpdateExportLimitRequest extends FormRequest
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
            'exports_per_hour' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['exports_per_hour' => __('console.security.export_limit.field')];
    }
}
