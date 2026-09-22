<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tenant = $this->tenant();
        $unit = $this->route('unit');

        return $unit instanceof Unit
            ? Gate::allows('update', [$unit, $tenant])
            : Gate::allows('create', [Unit::class, $tenant]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * L'unicite est composite : deux organisations peuvent avoir la meme unite, une seule
     * organisation ne peut pas l'avoir deux fois.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = $this->tenant();
        $unit = $this->route('unit');

        return [
            'name' => [
                'required', 'string', 'max:60',
                // `units` vit dans la base du locataire, deja active : l'unicite s'y verifie
                // directement, sans filtre a poser.
                Rule::unique('units', 'name')
                    ->ignore($unit instanceof Unit ? $unit->id : null),
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get the attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('units.fields.name'),
            'position' => __('units.fields.position'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
