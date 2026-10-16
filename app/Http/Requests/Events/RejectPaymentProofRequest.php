<?php

namespace App\Http\Requests\Events;

use App\Enums\ProofRejectionReason;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Rejet d'une preuve de paiement avec son motif (README ecran 18, decision du 2026-10-16) : un motif
 * du catalogue, et une precision ecrite pour « autre ».
 */
class RejectPaymentProofRequest extends FormRequest
{
    /**
     * Meme garde 404 que le reste de la file : une preuve d'un autre evenement ne revele rien.
     * L'autorisation se joue avant la validation, pour qu'un membre non autorise n'apprenne rien
     * des champs attendus.
     */
    public function authorize(): bool
    {
        $proof = $this->route('proof');
        $event = $this->route('event');
        $tenant = $this->route('tenant');

        abort_unless(
            $proof instanceof PaymentProof && $event instanceof Event && $tenant instanceof Tenant
                && $proof->registration->event_id === $event->id,
            404,
        );

        return Gate::allows('reject', [$proof, $tenant]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(ProofRejectionReason::class)],
            'note' => [
                Rule::requiredIf(fn () => $this->reason()?->requiresNote() === true),
                'nullable',
                'string',
                'max:300',
            ],
        ];
    }

    public function reason(): ?ProofRejectionReason
    {
        return ProofRejectionReason::tryFrom((string) $this->input('reason'));
    }
}
