<?php

namespace App\Http\Controllers\Events;

use App\Actions\PaymentProofs\RejectPaymentProof;
use App\Actions\PaymentProofs\ValidatePaymentProof;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\RegistrationCompanion;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La file de verification des preuves (README ecran 18), etape 6 de « Ordre de construction ».
 *
 * Vit sous l'evenement, comme le reste du back-office des evenements : une preuve n'a de sens
 * que rattachee a l'inscription d'un evenement precis.
 */
class PaymentProofController extends Controller
{
    /**
     * Display the proofs awaiting verification for the given event.
     */
    public function index(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('viewAny', [PaymentProof::class, $tenant]);

        $registrations = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::ProofSubmitted)
            ->with(['unit', 'companions.unit', 'latestProof.paymentAccount'])
            ->orderBy('created_at')
            ->get();

        return Inertia::render('events/proofs', [
            'tenant' => ['slug' => $tenant->slug],
            'event' => ['id' => $event->id, 'name' => $event->name],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'rows' => $registrations->map(fn (Registration $registration) => $this->row($registration)),
        ]);
    }

    /**
     * Validate the given proof : the registration is confirmed.
     */
    public function approve(Tenant $tenant, Event $event, PaymentProof $proof, ValidatePaymentProof $validate): RedirectResponse
    {
        Gate::authorize('approve', [$proof, $tenant]);
        $this->ensureBelongsToEvent($proof, $event);

        $validated = $validate->handle($proof, request()->user());

        Inertia::flash('toast', $validated
            ? ['type' => 'success', 'message' => __('proofs.flash.validated')]
            : ['type' => 'error', 'message' => __('proofs.flash.no_longer_pending')]);

        return to_route('tenants.events.proofs.index', [$tenant, $event]);
    }

    /**
     * Reject the given proof : the registration returns to the unfinalized statuses, the guest
     * can retry once back on their reservation page (README 2.2).
     */
    public function reject(Tenant $tenant, Event $event, PaymentProof $proof, RejectPaymentProof $reject): RedirectResponse
    {
        Gate::authorize('reject', [$proof, $tenant]);
        $this->ensureBelongsToEvent($proof, $event);

        $rejected = $reject->handle($proof, request()->user());

        Inertia::flash('toast', $rejected
            ? ['type' => 'success', 'message' => __('proofs.flash.rejected')]
            : ['type' => 'error', 'message' => __('proofs.flash.no_longer_pending')]);

        return to_route('tenants.events.proofs.index', [$tenant, $event]);
    }

    /**
     * Stream the receipt capture of the given proof, recording who opened it (SECURITY.md H2).
     *
     * Remplace l'URL signee anonyme dans la file de preuves : une URL signee copiee dans un
     * message reste utilisable par n'importe qui jusqu'a son expiration, et son usage ne peut pas
     * etre attribue. Ici chaque ouverture exige une session membre autorisee et laisse une trace.
     */
    public function receipt(Tenant $tenant, Event $event, PaymentProof $proof): StreamedResponse
    {
        Gate::authorize('view', [$proof, $tenant]);
        $this->ensureBelongsToEvent($proof, $event);

        $media = $proof->getFirstMedia(PaymentProof::ReceiptCollection);
        abort_if($media === null, 404);

        activity()
            ->performedOn($proof)
            ->event('viewed')
            ->log('payment_proof.receipt_viewed');

        return Storage::disk($media->disk)->download(
            $media->getPathRelativeToRoot(),
            'recu-'.$proof->id.'.'.$media->extension,
            [
                'Cache-Control' => 'no-store, private',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => 'sandbox',
            ],
        );
    }

    /**
     * A proof resolved by route binding must belong to this event : a mismatched URL (event A,
     * proof of event B) is a 404, not a silent action on the wrong event.
     */
    private function ensureBelongsToEvent(PaymentProof $proof, Event $event): void
    {
        abort_unless($proof->registration->event_id === $event->id, 404);
    }

    /**
     * Meme route journalisee pour la preuve examinee et pour ses doublons (SECURITY.md H2), jamais
     * une URL signee anonyme.
     */
    private function receiptUrl(PaymentProof $proof, int $eventId): ?string
    {
        return $proof->hasMedia(PaymentProof::ReceiptCollection)
            ? route('tenants.events.proofs.receipt', [Tenant::current(), $eventId, $proof], absolute: false)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Registration $registration): array
    {
        $proof = $registration->latestProof;
        $duplicateImageProofs = $proof->duplicateImageProofs();

        return [
            'registrationId' => $registration->id,
            'proofId' => $proof->id,
            // Reference du dossier, distincte de `reference` plus bas (celle de la transaction).
            'registrationReference' => $registration->reference,
            'name' => $registration->name,
            'phone' => $registration->phone,
            'unit' => $registration->unit->name,
            'partySize' => $registration->party_size,
            'companions' => $registration->companions->sortBy('position')->map(fn (RegistrationCompanion $companion) => [
                'name' => $companion->name,
                'unit' => $companion->unit->name,
            ])->values()->all(),
            'amountDue' => $registration->amount_due,
            'submittedAt' => $proof->created_at?->toISOString(),
            'channelLabel' => $proof->channel->label(),
            'reference' => $proof->reference,
            'guestNote' => $proof->guest_note,
            'paymentAccountLabel' => $proof->paymentAccount->label,
            'receiptUrl' => $this->receiptUrl($proof, $registration->event_id),
            // Les autres versements qui portent la meme capture, pour que le tresorier les compare
            // avant de trancher plutot que de devoir les retrouver lui-meme.
            'duplicateImageMatches' => $duplicateImageProofs->map(fn (PaymentProof $match) => [
                'proofId' => $match->id,
                'registrationReference' => $match->registration->reference,
                'name' => $match->registration->name,
                'eventName' => $match->registration->event->name,
                'sameEvent' => $match->registration->event_id === $registration->event_id,
                'status' => $match->registration->status->value,
                'statusLabel' => $match->registration->status->label(),
                'submittedAt' => $match->created_at?->toISOString(),
                'reference' => $match->reference,
                'receiptUrl' => $this->receiptUrl($match, $match->registration->event_id),
            ])->values()->all(),
            'signals' => [
                'duplicateReference' => $proof->hasDuplicateReference(),
                'duplicateImage' => $duplicateImageProofs->isNotEmpty(),
                'referenceMissingFromStatement' => $proof->referenceMissingFromStatement(),
                'statementAmountMismatch' => $proof->hasStatementAmountMismatch(),
                // Pas un soupcon : une information que le tresorier doit lire avant de valider.
                'guestNote' => filled($proof->guest_note),
            ],
        ];
    }
}
