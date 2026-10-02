<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use App\Settings\SupportSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Regler les durees proposees pour un acces de support. Elles se saisissent en heures, separees par
 * des virgules (« 1, 4, 12, 24 ») : une a huit durees, chacune d'une heure a trois jours.
 */
class UpdateSupportDurationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('console.area', ConsoleArea::Security->value);
    }

    /**
     * Les erreurs se rattachent au champ saisi, pas a chaque duree : l'ecran n'a qu'un champ.
     */
    protected function prepareForValidation(): void
    {
        $typed = $this->input('durations');

        $this->merge([
            'durations' => is_string($typed)
                ? array_values(array_filter(array_map(trim(...), explode(',', $typed)), fn (string $part) => $part !== ''))
                : [],
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'durations' => ['required', 'array', 'min:1', 'max:8', function (string $attribute, mixed $value, \Closure $fail) {
                foreach ((array) $value as $hours) {
                    if (! is_string($hours) || ! ctype_digit($hours) || (int) $hours < 1 || (int) $hours > SupportSettings::MaxHours) {
                        $fail(__('console.security.support_durations.invalid', ['max' => SupportSettings::MaxHours]));

                        return;
                    }
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'durations.required' => __('console.security.support_durations.invalid', ['max' => SupportSettings::MaxHours]),
            'durations.max' => __('console.security.support_durations.too_many'),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function durations(): array
    {
        return array_map(intval(...), (array) $this->validated('durations'));
    }
}
