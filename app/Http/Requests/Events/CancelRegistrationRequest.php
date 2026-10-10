<?php

namespace App\Http\Requests\Events;

use App\Data\RefundDecision;
use App\Enums\RefundStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Annulation d'une inscription par l'organisation (etape 9, ecran 20), avec le sort de son
 * paiement quand elle etait validee (README 2.11).
 */
class CancelRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Meme garde 404 que `AssignSeatingTableRequest` : une inscription qui n'appartient pas a
     * cet evenement ne doit rien reveler d'elle-meme avant meme la verification de permission.
     *
     * Choisir autre chose que « a rembourser » est une decision d'argent : elle exige la
     * permission de remboursement en plus de celle d'annuler, et se joue ici, avant la
     * validation, pour qu'un membre non autorise n'apprenne rien des champs attendus.
     */
    public function authorize(): bool
    {
        $registration = $this->registration();

        abort_unless($registration->event_id === $this->event()->id, 404);

        if (! Gate::allows('cancel', [$registration, $this->tenant()])) {
            return false;
        }

        $status = $this->input('refund.status');

        return $status === null
            || $status === RefundStatus::Due->value
            || Gate::allows('refund', [$registration, $this->tenant()]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            ...RefundRules::for($this->registration(), 'refund.', optionalStatus: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => __('registrations.modals.cancel.reason_label'),
            ...RefundRules::attributes('refund.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RefundRules::messages('refund.');
    }

    /**
     * Get what the organisation decided about the payment, or null to leave the default.
     */
    public function refundDecision(): ?RefundDecision
    {
        if (! $this->registration()->hasCollectedPayment()) {
            return null;
        }

        return RefundRules::decision($this->validated('refund') ?? []);
    }

    private function registration(): Registration
    {
        $registration = $this->route('registration');

        abort_unless($registration instanceof Registration, 404);

        return $registration;
    }

    private function event(): Event
    {
        $event = $this->route('event');

        abort_if(! $event instanceof Event, 404);

        return $event;
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
