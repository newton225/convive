<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Rules\UniqueTenantInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTenantInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueTenantInvitation($tenant)],
            'profile_id' => [
                'required',
                'integer',
                // `profiles` vit dans la base du locataire, deja active a ce point de la
                // requete (EnsureTenantMembership) : verifier l'existence dans cette table
                // revient a verifier qu'il appartient bien a ce locataire.
                // Un profil masque n'est propose nulle part (`Profile::scopeAssignable()`).
                Rule::exists('profiles', 'id')->whereNull('hidden_at'),
            ],
        ];
    }
}
