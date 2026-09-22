<?php

namespace App\Http\Controllers\Events;

use App\Actions\Events\SaveEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\UpdateEventSettingsRequest;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Les reglages d'un evenement (README ecran 24) : identite visuelle, places, echeances, rappels,
 * regles.
 *
 * Trois regles restent posees mais non appliquees (`rule_allow_without_proof`,
 * `rule_proof_legibility`, `rule_temporary_hold`) : leur sens exact n'est pas assez precis dans
 * le README pour deviner le comportement qu'elles devraient gouverner sans risquer une regle
 * fausse. Elles s'enregistrent, l'ecran le dit, rien ne les lit encore ailleurs.
 */
class EventSettingsController extends Controller
{
    /**
     * Display the settings of the given event.
     */
    public function edit(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('update', [$event, $tenant]);

        return Inertia::render('events/settings', [
            'tenant' => ['slug' => $tenant->slug],
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'tableCount' => $event->table_count,
                'seatsPerTable' => $event->seats_per_table,
                'capacity' => $event->capacity(),
                'registrationDeadline' => $event->registration_deadline?->toISOString(),
                'purgeAt' => $event->purge_at?->toISOString(),
                'invitationsSendAt' => $event->invitations_send_at?->toISOString(),
                'holdDurationMinutes' => $event->hold_duration_minutes,
            ],
            'colors' => $tenant->brandingOrCreate()->colors(),
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'reminders' => [
                'd7' => $event->reminder_j7_enabled,
                'd2' => $event->reminder_j2_enabled,
                'd1' => $event->reminder_j1_enabled,
                'dayOf' => $event->reminder_day_of_enabled,
            ],
            'rules' => [
                'scheduledSend' => $event->rule_scheduled_send,
                'autoSeating' => $event->rule_auto_seating,
                'allowWithoutProof' => $event->rule_allow_without_proof,
                'proofLegibility' => $event->rule_proof_legibility,
                'purgeOnExhaustion' => $event->rule_purge_on_exhaustion,
                'temporaryHold' => $event->rule_temporary_hold,
            ],
            // README ecran 24 : ces trois regles s'enregistrent mais ne gouvernent encore rien
            // (voir le commentaire de classe). L'ecran l'affiche plutot que de laisser croire
            // qu'elles agissent deja.
            'unenforcedRules' => ['allowWithoutProof', 'proofLegibility', 'temporaryHold'],
        ]);
    }

    /**
     * Update the reminders and rules of the given event.
     */
    public function update(UpdateEventSettingsRequest $request, Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        $save->settings($event, [
            'reminder_j7_enabled' => $request->boolean('reminder_j7_enabled'),
            'reminder_j2_enabled' => $request->boolean('reminder_j2_enabled'),
            'reminder_j1_enabled' => $request->boolean('reminder_j1_enabled'),
            'reminder_day_of_enabled' => $request->boolean('reminder_day_of_enabled'),
            'rule_scheduled_send' => $request->boolean('rule_scheduled_send'),
            'rule_auto_seating' => $request->boolean('rule_auto_seating'),
            'rule_allow_without_proof' => $request->boolean('rule_allow_without_proof'),
            'rule_proof_legibility' => $request->boolean('rule_proof_legibility'),
            'rule_purge_on_exhaustion' => $request->boolean('rule_purge_on_exhaustion'),
            'rule_temporary_hold' => $request->boolean('rule_temporary_hold'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('event_settings.flash.updated')]);

        return to_route('tenants.events.settings.edit', [$tenant, $event]);
    }
}
