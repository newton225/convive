<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use App\Models\TenantLimit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Regler les limites propres a une organisation (README section 3). Un champ vide rend la main au
 * plan ; aucune valeur ne veut dire « illimite » ici, c'est le plan qui le dit.
 */
class UpdateOrganisationLimitsRequest extends FormRequest
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
        return collect(TenantLimit::Quotas)
            ->mapWithKeys(fn (string $quota) => [$quota => ['nullable', 'integer', 'min:1', 'max:100000000']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(TenantLimit::Quotas)
            ->mapWithKeys(fn (string $quota) => [$quota => __("console.plans.fields.{$quota}")])
            ->all();
    }

    /**
     * Get the limits to store, every quota present : an absent or empty one follows the plan.
     *
     * @return array<string, int|null>
     */
    public function limits(): array
    {
        return collect(TenantLimit::Quotas)
            ->mapWithKeys(fn (string $quota) => [
                $quota => $this->validated($quota) === null ? null : (int) $this->validated($quota),
            ])
            ->all();
    }
}
