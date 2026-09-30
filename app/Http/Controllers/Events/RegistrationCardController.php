<?php

namespace App\Http\Controllers\Events;

use App\Actions\Tickets\RecordCardShare;
use App\Actions\Tickets\SendInvitationCard;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\RecordCardShareRequest;
use App\Http\Requests\Events\SendInvitationCardRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * La carte d'invitation, a la main depuis la base d'inscrits (README 2.7) : quand l'envoi
 * automatique n'a pas fonctionne ou qu'un invite a perdu sa carte.
 */
class RegistrationCardController extends Controller
{
    /**
     * Send, or send again, the invitation card from the application.
     */
    public function send(SendInvitationCardRequest $request, Tenant $tenant, Event $event, Registration $registration, SendInvitationCard $send): RedirectResponse
    {
        if ($registration->status !== RegistrationStatus::Confirmed) {
            return back()->withErrors(['card' => __('registrations.card.errors.not_confirmed')]);
        }

        // Faux : lien impossible a construire (sous-domaine, lien public) ou quota du plan atteint.
        if (! $send->handle($registration, $request->user())) {
            return back()->withErrors(['card' => __('registrations.card.errors.not_sent')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('registrations.card.flash.sent', ['name' => $registration->name])]);

        return to_route('tenants.events.registrations.index', [$tenant, $event]);
    }

    /**
     * Record that a card or ticket link was copied, or opened in the organiser's own WhatsApp.
     */
    public function shared(RecordCardShareRequest $request, Tenant $tenant, Event $event, Registration $registration, RecordCardShare $record): RedirectResponse
    {
        $record->handle($registration, $request->ticket(), $request->validated('via'), $request->user());

        return back();
    }
}
