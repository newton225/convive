<?php

namespace App\Http\Controllers\Events;

use App\Actions\Events\SaveEvent;
use App\Actions\Seating\SyncSeatingTables;
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
                // Les tables n'ont pas toutes la meme taille (decision du 2026-09-29) : la salle
                // se lit en groupes, et le nombre de tables est celui du plan reel.
                'seatsAtTables' => $event->seatsAtTables(),
                'tableGroups' => $groups = SyncSeatingTables::groupsOf($event),
                'tableCount' => array_sum(array_column($groups, 'count')),
                'capacity' => $event->capacity(),
                'registrationDeadline' => $event->registration_deadline?->toISOString(),
                'purgeAt' => $event->purge_at?->toISOString(),
                'invitationsSendAt' => $event->invitations_send_at?->toISOString(),
                'holdDurationMinutes' => $event->hold_duration_minutes,
                'visualUrl' => $event->visualUrl(),
            ],
            // Les couleurs que verront les invites de cet evenement : les siennes s'il en a, sinon
            // celles de la marque de l'organisation.
            'colors' => $event->colors(),
            'colorsOverridden' => $event->primary_color !== null || $event->secondary_color !== null,
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
                'phoneVerification' => $event->rule_phone_verification,
                'showRemainingSeats' => $event->rule_show_remaining_seats,
                'botProtection' => $event->rule_bot_protection,
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
            'rule_phone_verification' => $request->boolean('rule_phone_verification'),
            'rule_show_remaining_seats' => $request->boolean('rule_show_remaining_seats'),
            'rule_bot_protection' => $request->boolean('rule_bot_protection'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('event_settings.flash.updated')]);

        return to_route('tenants.events.settings.edit', [$tenant, $event]);
    }
}
