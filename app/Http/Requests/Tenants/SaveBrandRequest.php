<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveBrandRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('updateBrand', $this->tenant());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Les couleurs de marque sont recues en hexadecimal : c'est ce que produit un champ
     * `input[type=color]`, et c'est ce que consomment le billet et les emails, ou les
     * fonctions de couleur modernes ne sont pas fiables.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => __('organisation.errors.color'),
            'secondary_color.regex' => __('organisation.errors.color'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
