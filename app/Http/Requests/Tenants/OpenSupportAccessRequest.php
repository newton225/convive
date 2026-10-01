<?php

namespace App\Http\Requests\Tenants;

use App\Http\Controllers\Tenants\SupportAccessController;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Ouvrir un acces de support (README ecran 25) : reserve au Proprietaire, a une personne de
 * l'equipe Convive, pour l'une des durees proposees (24 heures au plus), avec son motif.
 */
class OpenSupportAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('manageSupportAccess', $this->tenant());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'operator_id' => ['required', 'integer', Rule::in(SupportAccessController::operators()->modelKeys())],
            'duration' => ['required', 'integer', Rule::in(SupportAccessGrant::DurationsInHours)],
            // Pourquoi l'acces est ouvert : la personne de l'equipe Convive sait quoi regarder, et
            // l'organisation garde la trace de ce qu'elle a autorise.
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operator_id.required' => __('support_access.errors.operator'),
            'operator_id.in' => __('support_access.errors.operator'),
            'duration.in' => __('support_access.errors.duration'),
            'reason.required' => __('support_access.errors.reason'),
            'reason.min' => __('support_access.errors.reason'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
