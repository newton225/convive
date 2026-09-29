<?php

namespace App\Http\Requests\Public;

use App\Models\Event;
use App\Rules\GuestPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Parcours invite non authentifie (README ecran 4) : n'importe qui muni du lien peut
     * s'inscrire, l'autorisation ne porte pas sur l'identite de l'appelant.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ramene le telephone a sa forme unique avant validation : c'est elle qui est enregistree et
     * comparee (une seule reservation active par numero, attente apres expirations, SECURITY.md
     * C3). Un numero invalide reste tel quel, et la regle le refuse.
     */
    protected function prepareForValidation(): void
    {
        if ($normalized = PhoneNumber::normalize($this->input('phone'))) {
            $this->merge(['phone' => $normalized]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // `units` vit dans la base du locataire, deja active par `InitializeTenancyBySubdomain` :
        // verifier l'existence ici revient a verifier qu'elle appartient a ce locataire, et
        // `active()` ecarte celles retirees de la liste (voir CLAUDE.md, « Unites »).
        $activeUnit = Rule::exists('units', 'id')->where('is_active', true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', new GuestPhoneNumber],
            // Facultatif (README 2.5) : WhatsApp, deja garanti par le telephone, reste le canal
            // systematique de la carte d'invitation (2.7), l'email s'y ajoute quand fourni.
            'email' => ['nullable', 'string', 'email', 'max:255'],
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
            'email' => __('guest.registration.fields.email'),
            'unit_id' => __('guest.registration.fields.unit'),
            'companions.*.name' => __('guest.registration.fields.companion_name'),
            'companions.*.unit_id' => __('guest.registration.fields.unit'),
        ];
    }

    /**
     * Resolve the event of the public link this request was submitted on.
     *
     * Le controleur revalide independamment (publie, ouvert aux inscriptions) : cette
     * resolution ne sert qu'a poser le plafond d'accompagnateurs, propre a chaque evenement.
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
