<?php

namespace App\Http\Requests\Public;

use App\Enums\PaymentChannel;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Rules\ImagePixelBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class StorePaymentProofRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Parcours invite non authentifie (README ecran 5) : n'importe qui muni du lien de reprise
     * peut deposer une preuve, l'autorisation ne porte pas sur l'identite de l'appelant.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Seuls les comptes offerts sur ce lien public sont acceptables : verifier l'existence
        // brute laisserait passer un compte d'un autre evenement, voire (avant reencodage de
        // l'identifiant) d'un autre locataire.
        $publicAccountIds = $this->publicAccounts()->pluck('id');

        // Le canal est celui du compte choisi, un pour un (decision du proprietaire du projet,
        // 2026-09-29) : l'invite ne le declare plus. Aucune reference pour un compte en especes
        // (`PaymentChannel::hasAccountNumber()`) : aucune transaction n'existe alors a declarer.
        $channel = $this->paymentAccount()?->channel;

        return [
            'payment_account_id' => ['required', 'integer', Rule::in($publicAccountIds)],
            'reference' => [$channel?->hasAccountNumber() ?? true ? 'required' : 'nullable', 'string', 'max:255'],
            // Une precision libre de l'invite (paiement en deux fois, envoi par un proche...) :
            // facultative, elle allume un signal dans la file des preuves quand elle est remplie.
            'guest_note' => ['nullable', 'string', 'max:500'],
            'receipt' => [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'max:5120',
                // Plafond haut, pas seulement un plancher (SECURITY.md H1, « limites de pixels
                // imposees ») : un fichier de quelques kilo-octets peut declarer des dimensions
                // demesurees et epuiser la memoire au reencodage GD sans jamais depasser 5 Mo.
                // Borner chaque cote ne suffit pas, c'est leur produit qui compte : `ImagePixelBudget`.
                'dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000',
                new ImagePixelBudget,
            ],
            'idempotency_key' => ['required', 'uuid'],
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
            'payment_account_id' => __('guest.proof.fields.payment_account'),
            'reference' => __('guest.proof.fields.reference'),
            'guest_note' => __('guest.proof.fields.guest_note'),
            'receipt' => __('guest.proof.fields.receipt'),
        ];
    }

    /**
     * Resolve the event of the public link this request was submitted on.
     *
     * Le controleur revalide independamment (publie, accepte encore des preuves via le
     * decompte) : cette resolution ne sert qu'a poser la liste des comptes offerts.
     */
    public function event(): Event
    {
        return once(fn () => Event::where('public_token', $this->route('token'))->firstOrFail());
    }

    /**
     * The payment account the guest chose, among those offered on this public link, if valid.
     */
    public function paymentAccount(): ?PaymentAccount
    {
        return $this->publicAccounts()->firstWhere('id', (int) $this->input('payment_account_id'));
    }

    /**
     * The channel of the chosen account, once the request has passed validation.
     */
    public function channel(): PaymentChannel
    {
        // Un compte visible sur le lien a forcement un canal vivant (`publiclyVisible`), et la
        // validation n'accepte que ces comptes-la : l'absence ici serait un bogue, pas une saisie.
        return $this->paymentAccount()->channel ?? throw new \LogicException('Compte de versement sans canal apres validation.');
    }

    /**
     * @return Collection<int, PaymentAccount>
     */
    private function publicAccounts(): Collection
    {
        return once(fn () => $this->event()->paymentAccounts()->publiclyVisible()->get());
    }
}
