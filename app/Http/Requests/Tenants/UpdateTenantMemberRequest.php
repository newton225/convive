<?php

namespace App\Http\Requests\Tenants;

use App\Models\Profile;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTenantMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * L'autorisation passe avant la validation : un refus doit rester un refus, pas un
     * message d'erreur qui renseigne sur les profils disponibles.
     */
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof User
            && Gate::allows('assign', [Profile::class, $this->tenant(), $member]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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

    /**
     * Configure the validator instance.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('profile_id')) {
                    return;
                }

                $this->rejectProfileStrongerThanTheActor($validator);
            },
        ];
    }

    /**
     * Affecter un profil revient a accorder ses permissions. On ne peut donc pas affecter
     * un profil qui detient plus que ce que l'on detient soi-meme, sinon la gestion des
     * profils devient un chemin d'elevation de privileges.
     */
    private function rejectProfileStrongerThanTheActor(Validator $validator): void
    {
        $tenant = $this->tenant();

        $profile = Profile::query()->find($this->input('profile_id'));

        if (! $profile instanceof Profile) {
            return;
        }

        $granted = array_diff(
            $profile->permissionValues(),
            $this->user()->tenantPermissionValues($tenant),
        );

        if ($granted !== []) {
            $validator->errors()->add('profile_id', __('profiles.errors.profile_stronger_than_actor'));
        }
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
