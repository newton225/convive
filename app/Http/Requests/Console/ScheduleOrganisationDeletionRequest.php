<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Programmer la suppression d'une organisation (README section 3) : uniquement a sa demande
 * ecrite, dont la reference est exigee et gardee au journal. Le nom de l'organisation se retape,
 * pour qu'on ne programme pas la suppression de la fiche voisine.
 */
class ScheduleOrganisationDeletionRequest extends FormRequest
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
        $tenant = $this->route('tenant');

        return [
            'request_reference' => ['required', 'string', 'min:5', 'max:255'],
            'confirmation' => ['required', 'string', Rule::in([$tenant instanceof Tenant ? $tenant->name : null])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => __('console.organisation.errors.confirmation_mismatch'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'request_reference' => __('console.organisation.fields.request_reference'),
            'confirmation' => __('console.organisation.fields.confirmation'),
        ];
    }
}
