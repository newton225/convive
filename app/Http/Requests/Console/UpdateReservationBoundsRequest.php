<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use App\Settings\ReservationSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Regler les bornes de la duree de reservation, en minutes : un minimum et un maximum, entre une
 * minute et une journee.
 */
class UpdateReservationBoundsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('console.area', ConsoleArea::Security->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'min' => ['required', 'integer', 'min:'.ReservationSettings::Floor, 'max:'.ReservationSettings::Ceiling],
            'max' => ['required', 'integer', 'gte:min', 'max:'.ReservationSettings::Ceiling],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'min' => __('console.security.reservation_bounds.min'),
            'max' => __('console.security.reservation_bounds.max'),
        ];
    }
}
