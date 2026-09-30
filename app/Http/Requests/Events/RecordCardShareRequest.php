<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Trace d'un lien de carte ou de billet copie, ou envoye par le WhatsApp de l'organisateur
 * (README 2.7). `ticket` nul : la carte de l'invite principal ; sinon, le billet d'un
 * accompagnateur, qui doit appartenir a cette inscription.
 */
class RecordCardShareRequest extends FormRequest
{
    public const Channels = ['copy', 'whatsapp'];

    public function authorize(): bool
    {
        $registration = $this->registration();
        $event = $this->route('event');
        $tenant = $this->route('tenant');

        abort_unless($event instanceof Event && $tenant instanceof Tenant && $registration->event_id === $event->id, 404);

        return Gate::allows('sendCard', [$registration, $tenant]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket' => [
                'nullable',
                'integer',
                Rule::exists('tickets', 'id')
                    ->where('registration_id', $this->registration()->id)
                    ->where(fn ($query) => $query->where('holder_position', '>', Ticket::GuestPosition)),
            ],
            'via' => ['required', Rule::in(self::Channels)],
        ];
    }

    public function ticket(): ?Ticket
    {
        $id = $this->validated('ticket');

        return $id === null ? null : Ticket::whereKey($id)->firstOrFail();
    }

    private function registration(): Registration
    {
        $registration = $this->route('registration');

        abort_unless($registration instanceof Registration, 404);

        return $registration;
    }
}
