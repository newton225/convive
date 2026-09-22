<?php

namespace App\Http\Requests\Events;

use App\Models\StatementImport;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Import d'un releve CSV (etape 9, ecran 19).
 */
class ImportStatementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return Gate::allows('import', [StatementImport::class, $tenant]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Le type est verifie sur le contenu (`mimes`), pas sur le nom. 2 Mo suffisent largement
     * pour `ImportReconciliationStatement::MaxRows` lignes.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
