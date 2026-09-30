<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Envoi manuel de la carte d'invitation depuis la base d'inscrits (README 2.7).
 */
class SendInvitationCardRequest extends FormRequest
{
    /**
     * Meme garde 404 que l'annulation : une inscription d'un autre evenement ne revele rien.
     */
    public function authorize(): bool
    {
        $registration = $this->route('registration');
        $event = $this->route('event');
        $tenant = $this->route('tenant');

        abort_unless(
            $registration instanceof Registration && $event instanceof Event && $tenant instanceof Tenant
                && $registration->event_id === $event->id,
            404,
        );

        return Gate::allows('sendCard', [$registration, $tenant]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
