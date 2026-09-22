<?php

namespace App\Http\Requests\Public;

use App\Enums\PaymentChannel;
use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
        $channel = PaymentChannel::tryFrom((string) $this->input('channel'));

        // Seuls les comptes offerts sur ce lien public sont acceptables : verifier l'existence
        // brute laisserait passer un compte d'un autre evenement, voire (avant reencodage de
        // l'identifiant) d'un autre locataire.
        $publicAccountIds = $this->event()->paymentAccounts()->publiclyVisible()->pluck('payment_accounts.id');

        return [
            'payment_account_id' => ['required', 'integer', Rule::in($publicAccountIds)],
            'channel' => ['required', Rule::in(PaymentChannel::values())],
            // Nulle uniquement pour un versement en especes (`PaymentChannel::hasAccountNumber()`) :
            // aucune reference de transaction n'existe alors a declarer.
            'reference' => [$channel?->hasAccountNumber() ?? true ? 'required' : 'nullable', 'string', 'max:255'],
            'amount_declared' => ['required', 'integer', 'min:1'],
            'receipt' => [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=100,min_height=100',
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
            'channel' => __('guest.proof.fields.channel'),
            'reference' => __('guest.proof.fields.reference'),
            'amount_declared' => __('guest.proof.fields.amount_declared'),
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
}
