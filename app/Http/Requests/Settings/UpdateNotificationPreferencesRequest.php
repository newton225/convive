<?php

namespace App\Http\Requests\Settings;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le canal choisi pour chaque type d'alerte (README section 5, ecran 25).
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Chacun ne regle que ses propres preferences : la route est deja derriere `auth`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Les cles sont restreintes au catalogue ferme de `NotificationType` (`array:`) : un type
     * invente ne cree pas de ligne.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array:'.implode(',', array_column(NotificationType::cases(), 'value'))],
            'preferences.*' => ['required', 'string', Rule::in(NotificationChannel::values())],
        ];
    }
}
