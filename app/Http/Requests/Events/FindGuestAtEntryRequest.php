<?php

namespace App\Http\Requests\Events;

use App\Models\ScanEvent;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Recherche d'un invite a l'entree, quand son QR ne peut pas etre lu (README ecran 26).
 */
class FindGuestAtEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Deux permissions : savoir scanner, et pouvoir faire entrer sans scan. Le QR prouve que
     * l'invite detient son billet ; un nom lu sur une liste ne prouve rien de tel.
     */
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return Gate::allows('perform', [ScanEvent::class, $tenant])
            && Gate::allows('admitWithoutScan', [ScanEvent::class, $tenant]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // La longueur minimale n'est pas une erreur de saisie : la recherche repond « trop
            // court » (`FindTicketsForEntry::MinimumLength`).
            'search' => ['nullable', 'string', 'max:80'],
        ];
    }
}
