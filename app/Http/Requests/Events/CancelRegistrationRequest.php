<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Annulation d'une inscription par l'organisation (etape 9, ecran 20).
 */
class CancelRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Meme garde 404 que `AssignSeatingTableRequest` : une inscription qui n'appartient pas a
     * cet evenement ne doit rien reveler d'elle-meme avant meme la verification de permission.
     */
    public function authorize(): bool
    {
        $registration = $this->route('registration');

        abort_unless(
            $registration instanceof Registration && $registration->event_id === $this->event()->id,
            404,
        );

        return Gate::allows('cancel', [$registration, $this->tenant()]);
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
        ];
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
