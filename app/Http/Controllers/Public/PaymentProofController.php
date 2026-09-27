<?php

namespace App\Http\Controllers\Public;

use App\Actions\PaymentProofs\SubmitPaymentProof;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StorePaymentProofRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Le depot de la preuve de paiement (README ecran 5 etape 2 et 3, ecran 6), etape 6 de
 * « Ordre de construction ». Meme absence d'authentification et meme cloisonnement par
 * sous-domaine que `Public\RegistrationController`.
 */
class PaymentProofController extends Controller
{
    /**
     * Store the proof for a reservation still holding its seats.
     *
     * README 2.2 : la relance paresseuse de l'expiration se fait ici comme dans
     * `RegistrationController::show()`, avant de tenter le depot, pour que le refus (README
     * 2.1) porte sur un statut deja a jour plutot que sur un decompte simplement ecoule sans
     * que la tache planifiee ne soit encore passee.
     */
    public function store(StorePaymentProofRequest $request, string $token, string $resume): RedirectResponse
    {
        $event = $this->publishedEvent($token);
        $registration = $this->registrationForResumeToken($event, $resume);

        if ($registration->holdHasExpired()) {
            $registration->update(['status' => RegistrationStatus::Expired]);
        }

        $proof = app(SubmitPaymentProof::class)->handle(
            $registration,
            [
                'payment_account_id' => (int) $request->validated('payment_account_id'),
                'channel' => $request->validated('channel'),
                'reference' => $request->validated('reference'),
                'amount_declared' => (int) $request->validated('amount_declared'),
            ],
            $request->file('receipt'),
            $request->validated('idempotency_key'),
        );

        // Sans preuve enregistree, le decompte etait ecoule : l'invite doit le savoir, la page
        // qu'il retrouve lui propose de relancer.
        Inertia::flash('toast', $proof !== null
            ? ['type' => 'success', 'message' => __('guest.flash.proof_sent')]
            : ['type' => 'error', 'message' => __('guest.flash.proof_too_late')]);

        return to_route('public.registrations.show', ['token' => $token, 'resume' => $resume]);
    }

    /**
     * Resolve the registration a resume token points to, scoped to this event.
     *
     * Meme methode que `RegistrationController::registrationForResumeToken()` : la recherche
     * compare l'empreinte, jamais le jeton en clair (CLAUDE.md, « Securite »).
     */
    private function registrationForResumeToken(Event $event, string $resume): Registration
    {
        $registration = Registration::where('event_id', $event->id)
            ->where('resume_token_hash', Registration::hashResumeToken($resume))
            ->first();

        abort_if(! $registration, 404);

        return $registration;
    }

    /**
     * Resolve the published event of a public link, or fail with the same 404 as an unknown
     * token (see `Public\EventController::show`).
     */
    private function publishedEvent(string $token): Event
    {
        $tenant = Tenant::current();
        abort_if(! $tenant, 404);

        $event = Event::where('public_token', $token)->first();
        abort_if(! $event || ! $event->isPublished(), 404);

        return $event;
    }
}
