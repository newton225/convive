<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Retirer une annonce de la vitrine (README section 3) : le motif est obligatoire, il est transmis
 * a l'organisation.
 */
class WithdrawAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('console.area', ConsoleArea::Showcase->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => __('console.showcase.fields.reason')];
    }
}
