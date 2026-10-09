<?php

namespace App\Http\Requests\Public;

use App\Enums\ClaimCategory;
use App\Models\GuestClaim;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La reclamation d'un invite sur son dossier (decision du 2026-10-09). Sans authentification : le
 * dossier se prouve par la signature de son lien (`Registration::notificationToken()`), verifiee
 * dans le controleur avant tout traitement.
 */
class StoreGuestClaimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(ClaimCategory::values())],
            'message' => ['required', 'string', 'min:10', 'max:'.GuestClaim::MessageMaxLength],
            'signature' => ['required', 'string'],
        ];
    }

    public function category(): ClaimCategory
    {
        return ClaimCategory::from((string) $this->validated('category'));
    }
}
