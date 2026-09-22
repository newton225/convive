<?php

namespace App\Http\Requests\Events;

use App\Models\SeatingTable;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Une regle de separation entre deux unites, pour cet evenement (README ecran 21, README 2.6).
 * Memes permissions que le placement manuel : geree par qui peut deja deplacer une inscription.
 */
class StoreUnitSeparationRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('assign', [SeatingTable::class, $this->tenant()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_id' => [
                'required',
                'integer',
                'different:other_unit_id',
                Rule::exists('units', 'id'),
            ],
            'other_unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unit_id' => __('seating.constraints.unit_a'),
            'other_unit_id' => __('seating.constraints.unit_b'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
