<?php

namespace App\Http\Requests\Events;

use App\Models\Tenant;
use App\Support\EventReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * L'ecran Tresorerie (decision du 2026-10-16) : les memes droits que le rapport d'un evenement
 * (permission `reports.view`, plan qui inclut les rapports), et une periode facultative.
 */
class TreasuryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant && Gate::allows('view', [EventReport::class, $tenant]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function from(): ?Carbon
    {
        return $this->validated('from') ? Carbon::parse($this->validated('from')) : null;
    }

    public function to(): ?Carbon
    {
        return $this->validated('to') ? Carbon::parse($this->validated('to')) : null;
    }
}
