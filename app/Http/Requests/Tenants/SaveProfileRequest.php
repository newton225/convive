<?php

namespace App\Http\Requests\Tenants;

use App\Enums\TenantPermission;
use App\Models\Profile;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * L'autorisation passe avant la validation : un membre sans la permission recoit un refus,
     * pas un message d'erreur qui lui apprendrait ce que le formulaire attend.
     */
    public function authorize(): bool
    {
        $tenant = $this->tenant();
        $profile = $this->route('profile');

        return $profile instanceof Profile
            ? Gate::allows('update', [$profile, $tenant])
            : Gate::allows('create', [Profile::class, $tenant]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = $this->tenant();
        $profile = $this->route('profile');

        return [
            'name' => [
                'required', 'string', 'max:255',
                // `profiles` vit dans la base du locataire, deja active : l'unicite s'y
                // verifie directement, sans filtre a poser.
                Rule::unique('profiles', 'name')
                    ->ignore($profile instanceof Profile ? $profile->id : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'requires_two_factor' => ['boolean'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(TenantPermission::values())],
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
                if ($validator->errors()->has('permissions') || $validator->errors()->hasAny(['permissions.*'])) {
                    return;
                }

                $this->rejectPermissionsTheActorDoesNotHold($validator);
            },
        ];
    }

    /**
     * A holder of profiles.manage cannot grant what they do not hold themselves.
     */
    private function rejectPermissionsTheActorDoesNotHold(Validator $validator): void
    {
        $tenant = $this->tenant();
        $held = $this->user()->tenantPermissionValues($tenant);

        $granted = array_diff((array) $this->input('permissions', []), $held);

        if ($granted !== []) {
            $validator->errors()->add('permissions', __('profiles.errors.permission_not_held'));
        }
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
