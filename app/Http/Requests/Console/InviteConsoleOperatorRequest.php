<?php

namespace App\Http\Requests\Console;

use App\Enums\ConsoleArea;
use App\Enums\ConsoleProfile;
use App\Models\ConsoleOperator;
use App\Support\Console\ConsoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Inviter une personne dans l'equipe editeur (README ecran 34) : reserve aux Fondateurs. L'adresse
 * est mise en minuscules avant validation, pour que l'unicite et la valeur stockee parlent de la
 * meme forme : c'est elle qui ouvrira la console.
 */
class InviteConsoleOperatorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('console.area', ConsoleArea::Team->value);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(ConsoleOperator::class, 'email'),
                // Un Fondateur de depart fait deja partie de l'equipe, sans ligne en base.
                Rule::notIn(array_filter(
                    [$this->input('email')],
                    fn ($email) => is_string($email) && ConsoleAccess::isBootstrapFounder($email),
                )),
            ],
            'profile' => ['required', Rule::enum(ConsoleProfile::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('console.team.errors.already_member'),
            'email.not_in' => __('console.team.errors.already_member'),
        ];
    }
}
