<?php

namespace App\Http\Requests\Events;

use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Purge des inscriptions non finalisees d'un evenement (README 2.4) : toutes, ou seulement les
 * lignes choisies a l'ecran. Sans `registrations`, la purge porte sur tout l'evenement.
 */
class PurgeRegistrationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Avant la validation : un membre sans le droit de purger n'apprend rien des champs attendus.
     */
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return Gate::allows('purge', [Registration::class, $tenant]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Vingt-cinq au plus : la taille d'une page de la liste, la selection ne porte jamais sur
     * plus que ce que l'ecran affiche.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registrations' => ['sometimes', 'array', 'min:1', 'max:25'],
            'registrations.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * The chosen registration ids, or null to purge the whole event.
     *
     * @return array<int, int>|null
     */
    public function chosenRegistrations(): ?array
    {
        if (! $this->has('registrations')) {
            return null;
        }

        return array_map('intval', $this->validated('registrations'));
    }
}
