<?php

namespace App\Http\Requests\Tenants;

use App\Enums\PaymentChannel;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePaymentAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tenant = $this->tenant();
        $account = $this->route('payment_account');

        return $account instanceof PaymentAccount
            ? Gate::allows('update', [$account, $tenant])
            : Gate::allows('create', [PaymentAccount::class, $tenant]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'channel' => ['required', Rule::enum(PaymentChannel::class)],
            'account_number' => ['nullable', 'string', 'max:60'],
            'holder_name' => ['nullable', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
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
                if ($validator->errors()->has('channel')) {
                    return;
                }

                $channel = PaymentChannel::tryFrom((string) $this->input('channel'));

                // Tout canal sauf les especes designe un compte : sans numero, l'invite n'a
                // nulle part ou verser.
                if ($channel?->hasAccountNumber() && blank($this->input('account_number'))) {
                    $validator->errors()->add(
                        'account_number',
                        __('payment_accounts.errors.account_number_required'),
                    );
                }
            },
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
            'label' => __('payment_accounts.fields.label'),
            'channel' => __('payment_accounts.fields.channel'),
            'account_number' => __('payment_accounts.fields.account_number'),
            'holder_name' => __('payment_accounts.fields.holder_name'),
            'instructions' => __('payment_accounts.fields.instructions'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
