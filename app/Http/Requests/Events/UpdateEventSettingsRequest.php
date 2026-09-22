<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Rappels et regles d'un evenement (README ecran 24). Une case decochee n'apparait pas dans le
 * formulaire soumis (comportement standard d'une case a cocher HTML) : chaque champ est
 * `boolean`, jamais `required`, et le controleur lit `$request->boolean(...)` plutot que
 * `validated()` pour que l'absence vaille faux.
 */
class UpdateEventSettingsRequest extends FormRequest
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
            'reminder_j7_enabled' => ['boolean'],
            'reminder_j2_enabled' => ['boolean'],
            'reminder_j1_enabled' => ['boolean'],
            'reminder_day_of_enabled' => ['boolean'],
            'rule_scheduled_send' => ['boolean'],
            'rule_auto_seating' => ['boolean'],
            'rule_allow_without_proof' => ['boolean'],
            'rule_proof_legibility' => ['boolean'],
            'rule_purge_on_exhaustion' => ['boolean'],
            'rule_temporary_hold' => ['boolean'],
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
