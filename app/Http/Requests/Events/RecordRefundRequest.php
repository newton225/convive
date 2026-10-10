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
 * Marquer comme rembourse une annulation « a rembourser » (README 2.11).
 */
class RecordRefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Meme garde 404 que l'annulation : une inscription d'un autre evenement ne revele rien.
     */
    public function authorize(): bool
    {
        $registration = $this->registration();
        $event = $this->route('event');
        $tenant = $this->route('tenant');

        abort_unless($event instanceof Event && $tenant instanceof Tenant && $registration->event_id === $event->id, 404);

        return Gate::allows('refund', [$registration, $tenant]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return RefundRules::for($this->registration(), '', optionalStatus: false);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return RefundRules::attributes('');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RefundRules::messages('');
    }

    public function refundDecision(): RefundDecision
    {
        return RefundRules::decision($this->validated(), RefundStatus::Refunded);
    }

    private function registration(): Registration
    {
        $registration = $this->route('registration');

        abort_unless($registration instanceof Registration, 404);

        return $registration;
    }
}
