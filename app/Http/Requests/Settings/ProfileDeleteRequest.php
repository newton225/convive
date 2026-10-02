<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * Une organisation conserve toujours au moins un Proprietaire (CLAUDE.md, « Profils et
     * permissions ») : le dernier ne supprime pas son compte, il transmet d'abord l'organisation
     * ou la supprime. L'espace personnel, lui, part avec le compte.
     *
     * Le message s'attache au champ du mot de passe, le seul du formulaire.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $orphaned = $this->user()->tenants()
                    ->where('is_personal', false)
                    ->get()
                    ->filter(fn (Tenant $tenant) => $this->user()->ownsTenant($tenant) && $tenant->owners()->count() === 1);

                if ($orphaned->isNotEmpty()) {
                    $validator->errors()->add('password', __('account.delete_account.last_owner', [
                        'organisations' => $orphaned->pluck('name')->implode(', '),
                    ]));
                }
            },
        ];
    }
}
