<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use App\Enums\PlanCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Changer le plan d'une organisation (README section 3). Le plan vient du catalogue ; ce qui rend
 * le changement possible ou non (quotas depasses, abonnement regle en ligne) est verifie par
 * `ManageOrganisation`.
 */
class ChangeOrganisationPlanRequest extends FormRequest
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
            'plan' => ['required', Rule::enum(PlanCode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['plan' => __('console.organisation.fields.plan')];
    }
}
