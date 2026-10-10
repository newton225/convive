<?php

namespace App\Http\Controllers\Events;

use App\Actions\PaymentProofs\RejectPaymentProof;
use App\Actions\PaymentProofs\ValidatePaymentProof;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantMembership;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\RegistrationCompanion;
use App\Models\Tenant;
use App\Support\ListPage;
use App\Support\ProofQueueEvents;
use App\Support\Search\UnaccentedSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        // Pagination, recherche, filtre et tri cote serveur (TODO du 2026-10-07, point 11). Par
        // defaut, les plus anciennes en tete : une file se traite dans l'ordre d'arrivee.
        $search = trim((string) $request->input('filter.search', ''));
        $signal = in_array($request->input('filter.signal'), self::SignalFilters, true) ? $request->input('filter.signal') : 'all';
        $sort = in_array($request->input('sort'), self::Sorts, true) ? $request->input('sort') : 'submitted_at';

        $query = $this->queue($event, $search, $sort)
            ->with(['unit', 'companions.unit', 'latestProof.paymentAccount', 'latestProof.media']);

        // Les signaux (reference ou capture deja vues, ecart avec le releve) se calculent en PHP :
        // la comparaison des captures n'a pas d'equivalent SQL. Filtrer sur eux passe donc par la
        // file de l'evenement, bornee par sa capacite ; le navigateur ne recoit toujours qu'une page.
        if ($signal === 'all') {
            $paginator = ListPage::of($query, $request);
            $this->prepareSignals($paginator->getCollection());
            $page = $paginator->through(fn (Registration $registration) => $this->row($registration));
        } else {
            $queue = $query->get();
            $this->prepareSignals($queue);

            $page = ListPage::ofCollection(
                $queue
                    ->map(fn (Registration $registration) => $this->row($registration))
                    ->filter(fn (array $row) => $this->matchesSignal($row['signals'], $signal))
                    ->values(),
                $request,
            );
        }

        return Inertia::render('events/proofs', [
            'tenant' => ['slug' => $tenant->slug],
            'event' => ['id' => $event->id, 'name' => $event->name, 'hasPassed' => $event->hasEnded()],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'rows' => collect($page->items())->values(),
            'meta' => ListPage::meta($page),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'signal' => $signal,
                'sort' => $sort,
            ],
            'hasProofs' => $this->queue($event, '', 'submitted_at')->exists(),
            // Passer d'un evenement a l'autre : un acces du support limite a un evenement ne voit que lui.
            'eventSwitcher' => ProofQueueEvents::all($event, $request->attributes->get(EnsureTenantMembership::SupportAccessAttribute)?->event_id),
        ]);
    }

    /**
     * Les filtres de signal de la file. Une anomalie est un soupcon ; la precision de l'invite n'en
     * est pas une, c'est une information a lire : les deux filtres restent distincts.
     */
    private const SignalFilters = ['all', 'anomaly', 'clean', 'note'];

    private const Sorts = ['submitted_at', '-submitted_at', 'name', '-name', 'amount_due', '-amount_due', 'party_size', '-party_size'];

    /**
     * The registrations waiting for their proof to be checked, searched and sorted.
     *
     * @return Builder<Registration>
     */
    private function queue(Event $event, string $search, string $sort): Builder
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        return Registration::query()
            ->where('event_id', $event->id)
            ->where('status', RegistrationStatus::ProofSubmitted)
            // Une inscription « preuve envoyee » sans preuve est une donnee incoherente : la file l'ignore
            // plutot que de renvoyer une erreur serveur a qui la parcourt (balayage de volume du 2026-10-10).
            ->whereHas('proofs')
            // Tout ce qui identifie une preuve : nom, reference du dossier et de la transaction,
            // telephone, unite, accompagnateurs.
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $any) => $any
                ->where(fn (Builder $own) => UnaccentedSearch::apply($own, ['name', 'reference', 'phone'], $search))
                ->orWhereHas('latestProof', fn (Builder $proof) => UnaccentedSearch::apply($proof, ['reference'], $search))
                ->orWhereHas('unit', fn (Builder $unit) => UnaccentedSearch::apply($unit, ['name'], $search))
                ->orWhereHas('companions', fn (Builder $companion) => UnaccentedSearch::apply($companion, ['name'], $search))))
            ->when(
                $column === 'submitted_at',
                fn (Builder $query) => $query->orderBy(
                    PaymentProof::query()
                        ->select('created_at')
                        ->whereColumn('registration_id', 'registrations.id')
                        ->latest('created_at')
                        ->limit(1),
                    $direction,
                ),
                fn (Builder $query) => $query->orderBy($column, $direction),
            )
            ->orderBy('id');
    }

    /**
     * @param  array<string, bool>  $signals
     */
    private function matchesSignal(array $signals, string $filter): bool
    {
        $anomaly = collect($signals)->except('guestNote')->contains(true);

        return match ($filter) {
            'anomaly' => $anomaly,
            'clean' => ! $anomaly,
            'note' => $signals['guestNote'],
            default => true,
        };
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
    /**
     * Calcule les signaux de toute la page en lot et relie chaque preuve a son inscription deja
     * chargee : sans cela, chaque ligne posait cinq a six requetes (`QueryBudgetTest`).
     *
     * @param  Collection<int, Registration>  $registrations
     */
    private function prepareSignals(Collection $registrations): void
    {
        $registrations->each(fn (Registration $registration) => $registration->latestProof?->setRelation('registration', $registration));

        PaymentProof::preloadSignals($registrations->map(fn (Registration $registration) => $registration->latestProof));
    }

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
