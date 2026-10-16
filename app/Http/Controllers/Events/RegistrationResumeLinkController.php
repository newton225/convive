<?php

namespace App\Http\Controllers\Events;

use App\Actions\Registrations\SendResumeLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ResendResumeLinkRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Le lien du dossier, renvoye a l'invite par l'organisation depuis la base d'inscrits : reservation
 * en cours ou preuve rejetee. Le lien neuf part au telephone du dossier, il n'est jamais affiche.
 */
class RegistrationResumeLinkController extends Controller
{
    /**
     * Send a fresh link to the guest, replacing the previous one.
     */
    public function send(ResendResumeLinkRequest $request, Tenant $tenant, Event $event, Registration $registration, SendResumeLink $send): RedirectResponse
    {
        // Faux : dossier sans lien a renvoyer (preuve en verification, valide, annule) ou quota du plan atteint.
        if (! $send->sendFor($registration, $request->user())) {
            return back()->withErrors(['resume_link' => __('registrations.resume_link.errors.not_sent')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('registrations.resume_link.flash.sent', ['name' => $registration->name])]);

        return to_route('tenants.events.registrations.index', [$tenant, $event]);
    }
}
