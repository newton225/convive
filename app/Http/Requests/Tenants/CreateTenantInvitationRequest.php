<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Rules\UniqueTenantInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateTenantInvitationRequest extends FormRequest
{
    /**
     * L'autorisation se joue avant la validation (CLAUDE.md) : un membre qui ne peut pas inviter ne
     * reçoit aucun message sur ce que le formulaire attendrait.
     */
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant && Gate::allows('inviteMember', $tenant);
    }

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
