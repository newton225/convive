<?php

namespace App\Http\Requests\Events;

use App\Models\ScanEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Verification d'un code scanne a l'entree (README ecran 26), etape 7 de « Ordre de
 * construction ».
 */
class ScanTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Le forcage exige sa propre permission (`scan.force`), en plus de `scan.perform` : un agent
     * qui peut scanner ne peut pas forcer une entree deja marquee du seul fait de savoir scanner.
     */
    public function authorize(): bool
    {
        $tenant = $this->tenant();

        if (! Gate::allows('perform', [ScanEvent::class, $tenant])) {
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
            'token' => ['required', 'string'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
