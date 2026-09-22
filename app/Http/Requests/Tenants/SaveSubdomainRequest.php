<?php

namespace App\Http\Requests\Tenants;

use App\Models\Tenant;
use App\Support\Subdomain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Stancl\Tenancy\Database\Models\Domain;

class SaveSubdomainRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('updateSubdomain', $this->tenant());
    }

    /**
     * Normalise the subdomain before validating it, so the uniqueness check and the stored
     * value agree on one form.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('subdomain'))) {
            $this->merge(['subdomain' => Subdomain::normalise($this->input('subdomain'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenant = $this->tenant();

        // Une fois un lien public distribue, le sous-domaine est fige : le modifier casserait
        // des adresses deja entre les mains des invites.
        if ($tenant->subdomain !== null && $tenant->hasPublishedEvent()) {
            return [
                'subdomain' => [
                    function (string $attribute, mixed $value, Closure $fail) use ($tenant): void {
                        if ($value !== $tenant->subdomain) {
                            $fail(__('organisation.errors.subdomain_frozen'));
                        }
                    },
                ],
            ];
        }

        return [
            'subdomain' => [
                'required', 'string',
                'min:'.Subdomain::MinimumLength,
                'max:'.Subdomain::MaximumLength,
                // Une etiquette DNS : lettres, chiffres et traits d'union, jamais en bordure.
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                // Le sous-domaine vit dans `domains` (stancl/tenancy, base centrale), pas dans
                // une colonne `tenants.subdomain` : `ignore` cible `tenant_id`, pas l'identifiant
                // de la ligne `domains` elle meme, puisqu'on exclut le domaine de CE locataire.
                Rule::unique(Domain::class, 'domain')->ignore($tenant->id, 'tenant_id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && Subdomain::isReserved($value)) {
                        $fail(__('organisation.errors.subdomain_reserved'));
                    }
                },
            ],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.regex' => __('organisation.errors.subdomain_format'),
            'subdomain.unique' => __('organisation.errors.subdomain_taken'),
        ];
    }

    /**
     * Get the attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['subdomain' => __('organisation.fields.subdomain')];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
