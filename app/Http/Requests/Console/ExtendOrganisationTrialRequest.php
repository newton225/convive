<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Offrir ou prolonger l'essai d'une organisation (README section 3). Une date de fin se choisit
 * dans l'avenir ; sans date, l'essai est sans fin.
 */
class ExtendOrganisationTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('console.area', ConsoleArea::OrganisationActions->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ends_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['ends_at' => __('console.organisation.fields.trial_ends_at')];
    }
}
