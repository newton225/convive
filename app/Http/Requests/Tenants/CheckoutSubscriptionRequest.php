<?php

namespace App\Http\Requests\Tenants;

use App\Enums\BillingCurrency;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Le choix d'un plan payant et de sa devise (README ecran 16). L'autorisation se joue avant la
 * validation : un membre sans la permission ne doit rien apprendre des devises acceptees.
 */
class CheckoutSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return Gate::allows('manage', [Subscription::class, $tenant]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Absente, la devise vaut la premiere devise proposee.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', Rule::in(BillingCurrency::enabledValues())],
        ];
    }

    /**
     * Get the chosen currency, or the default one.
     */
    public function currency(): BillingCurrency
    {
        return BillingCurrency::tryFrom((string) $this->validated('currency')) ?? BillingCurrency::default();
    }
}
