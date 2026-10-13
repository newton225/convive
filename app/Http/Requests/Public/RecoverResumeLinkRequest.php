<?php

namespace App\Http\Requests\Public;

use App\Rules\GuestPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * « Retrouver ma reservation » : l'invite saisit le numero de sa reservation, le lien part vers ce
 * telephone (`App\Actions\Registrations\SendResumeLink`).
 */
class RecoverResumeLinkRequest extends FormRequest
{
    /**
     * Parcours invite non authentifie : l'envoi va au telephone de la reservation, pas a l'appelant.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($normalized = PhoneNumber::normalize($this->input('phone'))) {
            $this->merge(['phone' => $normalized]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32', new GuestPhoneNumber],
        ];
    }
}
