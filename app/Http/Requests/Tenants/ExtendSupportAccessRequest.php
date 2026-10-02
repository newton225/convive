<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Settings\SupportSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Prolonger l'acces de support en cours (README ecran 25) : reserve a un Proprietaire, pour l'une
 * des durees proposees. Le plafond se joue dans `ManageSupportAccess::extend()`.
 */
class ExtendSupportAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return Gate::allows('manageSupportAccess', $tenant);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'duration' => ['required', 'integer', Rule::in(app(SupportSettings::class)->durations)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'duration.required' => __('support_access.errors.duration'),
            'duration.in' => __('support_access.errors.duration'),
        ];
    }
}
