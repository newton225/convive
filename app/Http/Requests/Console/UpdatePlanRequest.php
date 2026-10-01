<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Modifier un plan (README ecran 30). Un champ vide veut dire quelque chose : pas de prix, c'est
 * « sur devis » ; pas de quota, c'est « illimite ». Zero est un prix (gratuit), jamais un quota.
 *
 * Le franc CFA se saisit en francs, sans decimale. L'euro et le dollar se saisissent en unites
 * (69,00) et se stockent en centimes, comme le prestataire de paiement les attend.
 */
class UpdatePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('console.area', ConsoleArea::Plans->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'monthly_price' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'monthly_price_eur' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'monthly_price_usd' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'max_active_events' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_registrations' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'max_members' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'has_reconciliation' => ['required', 'boolean'],
            'has_reports' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['monthly_price', 'monthly_price_eur', 'monthly_price_usd', 'max_active_events', 'max_registrations', 'max_members'])
            ->mapWithKeys(fn (string $field) => [$field => __("console.plans.fields.{$field}")])
            ->all();
    }

    /**
     * Get the validated plan attributes, amounts in the unit they are stored in.
     *
     * @return array<string, int|bool|null>
     */
    public function attributesForPlan(): array
    {
        $cents = fn (mixed $amount): ?int => $amount === null ? null : (int) round(((float) $amount) * 100);
        $integer = fn (mixed $value): ?int => $value === null ? null : (int) $value;

        return [
            'monthly_price' => $integer($this->validated('monthly_price')),
            'monthly_price_eur' => $cents($this->validated('monthly_price_eur')),
            'monthly_price_usd' => $cents($this->validated('monthly_price_usd')),
            'max_active_events' => $integer($this->validated('max_active_events')),
            'max_registrations' => $integer($this->validated('max_registrations')),
            'max_members' => $integer($this->validated('max_members')),
            'has_reconciliation' => $this->boolean('has_reconciliation'),
            'has_reports' => $this->boolean('has_reports'),
        ];
    }
}
