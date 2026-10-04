<?php

namespace App\Http\Requests\Tenants;

use App\Enums\LegalForm;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use libphonenumber\PhoneNumberUtil;

class SaveLegalIdentityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * L'autorisation passe avant la validation : un membre sans la permission recoit un refus,
     * pas un message d'erreur qui lui apprendrait ce que le formulaire attend.
     */
    public function authorize(): bool
    {
        return Gate::allows('updateLegalIdentity', $this->tenant());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rien n'est obligatoire ici : l'identite legale se remplit par morceaux, et c'est
     * `Tenant::isReadyToPublish()` qui dit si elle suffit pour publier un lien public. Les
     * formats sont volontairement permissifs : le RCCM et le numero de contribuable varient
     * d'un pays a l'autre, un format trop strict refuserait des identifiants valides.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'legal_form' => ['nullable', Rule::enum(LegalForm::class)],
            'representative_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-\/ ]*$/'],
            'tax_number' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9\- ]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            // Le code du pays (`CI`), choisi dans une liste : c'est lui qui donne le nom du pays dans
            // la langue du document.
            'country' => ['nullable', 'string', Rule::in(PhoneNumberUtil::getInstance()->getSupportedRegions())],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ().-]{8,32}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('country'))) {
            $this->merge(['country' => strtoupper(trim($this->input('country')))]);
        }
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_number.regex' => __('organisation.errors.registration_number'),
            'tax_number.regex' => __('organisation.errors.tax_number'),
            'phone.regex' => __('organisation.errors.phone'),
        ];
    }

    /**
     * Get the attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'display_name' => __('organisation.fields.display_name'),
            'legal_name' => __('organisation.fields.legal_name'),
            'legal_form' => __('organisation.fields.legal_form'),
            'representative_name' => __('organisation.fields.representative_name'),
            'registration_number' => __('organisation.fields.registration_number'),
            'tax_number' => __('organisation.fields.tax_number'),
            'address' => __('organisation.fields.address'),
            'city' => __('organisation.fields.city'),
            'country' => __('organisation.fields.country'),
            'email' => __('organisation.fields.email'),
            'phone' => __('organisation.fields.phone'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
