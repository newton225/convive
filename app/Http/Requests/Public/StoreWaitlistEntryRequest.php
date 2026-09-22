<?php

namespace App\Http\Requests\Public;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWaitlistEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Parcours invite non authentifie (README ecran 10), comme l'inscription elle-meme.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Memes regles que `StoreRegistrationRequest` : la liste d'attente collecte les memes
     * informations, pour que l'invite n'ait rien a ressaisir une fois invite a finaliser.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $activeUnit = Rule::exists('units', 'id')->where('is_active', true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ().-]{8,32}$/'],
            'unit_id' => ['required', 'integer', $activeUnit],

            'companions' => ['array', 'max:'.$this->event()->companion_limit],
            'companions.*.name' => ['required', 'string', 'max:255'],
            'companions.*.unit_id' => ['required', 'integer', $activeUnit],
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
            'name' => __('guest.registration.fields.name'),
            'phone' => __('guest.registration.fields.phone'),
            'unit_id' => __('guest.registration.fields.unit'),
            'companions.*.name' => __('guest.registration.fields.companion_name'),
            'companions.*.unit_id' => __('guest.registration.fields.unit'),
        ];
    }

    /**
     * Resolve the event of the public link this request was submitted on.
     */
    public function event(): Event
    {
        return once(fn () => Event::where('public_token', $this->route('token'))->firstOrFail());
    }

    /**
     * Get the validated companions, ready to persist.
     *
     * @return array<int, array{name: string, unit_id: int}>
     */
    public function companions(): array
    {
        return $this->validated('companions', []);
    }
}
