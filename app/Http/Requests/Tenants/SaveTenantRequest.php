<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Rules\TenantName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveTenantRequest extends FormRequest
{
    /**
     * L'autorisation se joue avant la validation (CLAUDE.md) : un membre qui ne peut pas renommer
     * l'organisation ne lit pas ce que le formulaire attendrait. Sans organisation dans l'adresse, c'est
     * une creation : tout compte connecte ouvre la sienne.
     */
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return ! $tenant instanceof Tenant || Gate::allows('update', $tenant);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new TenantName],
        ];
    }
}
