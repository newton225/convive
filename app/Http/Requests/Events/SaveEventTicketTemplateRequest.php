<?php

namespace App\Http\Requests\Events;

use App\Enums\TicketModel;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Le gabarit du billet propre a un evenement (README ecran 15). Le modele n'est exige que si le
 * gabarit est active : desactive, seul le drapeau change et celui de l'organisation s'applique.
 */
class SaveEventTicketTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        abort_unless($event instanceof Event, 404);

        return Gate::allows('update', [$event, $this->tenant()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket_template_enabled' => ['required', 'boolean'],
            'ticket_model' => [
                Rule::requiredIf(fn () => $this->boolean('ticket_template_enabled')),
                'nullable',
                Rule::enum(TicketModel::class),
            ],
            'ticket_element_logo' => ['boolean'],
            'ticket_element_stamp' => ['boolean'],
            'ticket_element_signature' => ['boolean'],
            'ticket_element_companions' => ['boolean'],
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
