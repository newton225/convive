<?php

namespace App\Http\Requests\Events;

use App\Models\ScanEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Entree validee sans scan, sur un billet retrouve par la recherche (README ecran 26).
 */
class AdmitWithoutScanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Comme pour un billet scanne, le forcage garde sa propre permission (`scan.force`).
     */
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        if (! Gate::allows('perform', [ScanEvent::class, $tenant])
            || ! Gate::allows('admitWithoutScan', [ScanEvent::class, $tenant])) {
            return false;
        }

        return ! $this->boolean('force') || Gate::allows('force', [ScanEvent::class, $tenant]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ticket' => ['required', 'integer'],
            'force' => ['nullable', 'boolean'],
            'station' => ['nullable', 'string', 'max:60'],
        ];
    }
}
