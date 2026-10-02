<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use App\Enums\PlanCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Regler la periode d'essai (README section 3). Une duree vide veut dire un essai sans date de fin.
 */
class UpdateTrialSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('console.area', ConsoleArea::Plans->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'plan' => ['required', Rule::enum(PlanCode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'days' => __('console.trial.fields.days'),
            'plan' => __('console.trial.fields.plan'),
        ];
    }

    /**
     * @return array{enabled: bool, days: int|null, plan: string}
     */
    public function settings(): array
    {
        return [
            'enabled' => $this->boolean('enabled'),
            'days' => $this->validated('days') === null ? null : (int) $this->validated('days'),
            'plan' => (string) $this->validated('plan'),
        ];
    }
}
