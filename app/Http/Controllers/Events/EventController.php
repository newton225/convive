<?php

namespace App\Http\Controllers\Events;

use App\Actions\Events\SaveEvent;
use App\Actions\Seating\SyncSeatingTables;
use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Requests\Events\SaveEventRequest;
use App\Http\Requests\Events\SaveEventVisualRequest;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Settings\ReservationSettings;
use App\Support\GettingStarted;
use App\Support\ListPage;
use App\Support\PlanLimits;
use App\Support\Search\UnaccentedSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Display the events of the tenant.
     */
    public function index(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('viewAny', [Event::class, $tenant]);

        // Pagination, recherche et filtre cote serveur (TODO du 2026-10-07, point 11) : en cours et
        // a venir par defaut, un evenement termine ne demande plus rien.
        $status = in_array($request->input('filter.status'), ['active', 'closed', 'all'], true)
            ? $request->input('filter.status')
            : 'active';
        $search = trim((string) $request->input('filter.search', ''));

        $events = ListPage::of($this->listedEvents($request, $search, $status)->ordered()->with('paymentAccounts'), $request, ListPage::CardsPerPage);

        return Inertia::render('events/index', [
            'tenant' => $this->tenantPayload($tenant),
            'meta' => ListPage::meta($events),
            'filters' => ['status' => $status, 'search' => $search !== '' ? $search : null],
            // Les compteurs suivent la recherche : ils disent ou se trouvent les resultats.
            'counts' => [
                'active' => $this->listedEvents($request, $search, 'active')->count(),
                'closed' => $this->listedEvents($request, $search, 'closed')->count(),
                'all' => $this->listedEvents($request, $search, 'all')->count(),
            ],
            'hasEvents' => $this->listedEvents($request, '', 'all')->exists(),
            'events' => $events->getCollection()
                ->map(fn (Event $event) => [
                    ...$this->summary($event),
                    // README ecran 12 : de quoi juger d'un coup d'oeil ou agir, sans ouvrir l'evenement.
                    'visualUrl' => $event->visualUrl(),
                    'occupiedSeats' => $event->occupiedSeats(),
                    'collectedAmount' => $event->collectedAmount(),
                    'proofsToCheck' => $event->registrations()->where('status', RegistrationStatus::ProofSubmitted)->count(),
                ])->values(),
            'permissions' => $request->user()->toTenantPermissions($tenant),
        ]);
    }

    /**
     * The events of the list, for a search and a status filter (active, closed or all).
     *
     * @return Builder<Event>
     */
    private function listedEvents(Request $request, string $search, string $status): Builder
    {
        return Event::query()
            // Un acces de support limite a un evenement ne voit que lui (README ecran 25).
            ->when($this->supportEventId($request), fn (Builder $query, int $eventId) => $query->whereKey($eventId))
            // Le nom, le sous-titre et le lieu : ce qu'on se rappelle d'un evenement qu'on cherche.
            ->when($search !== '', fn (Builder $query) => UnaccentedSearch::apply($query, ['name', 'subtitle', 'venue'], $search))
            ->when($status === 'closed', fn (Builder $query) => $query->where('status', EventStatus::Closed))
            ->when($status === 'active', fn (Builder $query) => $query->where('status', '!=', EventStatus::Closed));
    }

    /**
     * The translated names of what is missing, as a sentence fragment.
     *
     * @param  array<int, string>  $keys
     */
    private function listOf(string $group, array $keys): string
    {
        return implode(', ', array_map(fn (string $key) => __("{$group}.{$key}"), $keys));
    }

    /**
     * Get the only event a limited support access may read, or null for a member or a full access.
     */
    private function supportEventId(Request $request): ?int
    {
        $grant = $request->attributes->get(EnsureTenantMembership::SupportAccessAttribute);

        return $grant instanceof SupportAccessGrant ? $grant->event_id : null;
    }

    /**
     * Show the wizard for a new event.
     */
    public function create(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('create', [Event::class, $tenant]);

        return Inertia::render('events/form', [
            'tenant' => $this->tenantPayload($tenant),
            'event' => null,
            'paymentAccounts' => $this->paymentAccounts(),
            'defaults' => [
                'companionLimit' => Event::MaximumCompanionLimit,
                'holdDurationMinutes' => app(ReservationSettings::class)->clamp(Event::DefaultHoldDurationMinutes),
                'holdDurationMin' => app(ReservationSettings::class)->hold_min_minutes,
                'holdDurationMax' => app(ReservationSettings::class)->hold_max_minutes,
            ],
            'tenantColors' => $tenant->brandingOrCreate()->colors(),
            'templates' => $this->templates(),
            'template' => $this->template($request->integer('from')),
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(SaveEventRequest $request, Tenant $tenant, SaveEvent $save): RedirectResponse
    {
        $event = $save->handle(null, $this->attributes($request), $this->accountIds($request), $request->tablePlan(), $request->priceCategories());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.created')]);

        return GettingStarted::redirect($request, $tenant, to_route('tenants.events.edit', [$tenant, $event]));
    }

    /**
     * Show the wizard for an existing event.
     */
    public function edit(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('view', [$event, $tenant]);

        return Inertia::render('events/form', [
            'tenant' => $this->tenantPayload($tenant),
            'event' => $this->details($event),
            'paymentAccounts' => $this->paymentAccounts(),
            'defaults' => [
                'companionLimit' => Event::MaximumCompanionLimit,
                'holdDurationMinutes' => app(ReservationSettings::class)->clamp(Event::DefaultHoldDurationMinutes),
                'holdDurationMin' => app(ReservationSettings::class)->hold_min_minutes,
                'holdDurationMax' => app(ReservationSettings::class)->hold_max_minutes,
            ],
            'tenantColors' => $tenant->brandingOrCreate()->colors(),
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(SaveEventRequest $request, Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        $save->handle($event, $this->attributes($request), $this->accountIds($request), $request->tablePlan(), $request->priceCategories());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.updated')]);

        return to_route('tenants.events.edit', [$tenant, $event]);
    }

    /**
     * Hand out the public link of the event.
     */
    public function publish(Request $request, Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        Gate::authorize('publish', [$event, $tenant]);

        // Avant la garde « pret a publier » : un espace au plafond de son plan n'a pas a
        // completer son dossier pour apprendre qu'il ne peut pas publier.
        if (! PlanLimits::for($tenant)->canPublishEvent()) {
            return back()->withErrors(['event' => __('billing.errors.event_quota', ['plan' => $tenant->plan()->name])]);
        }

        if ($missing = $event->missingBeforePublishing()) {
            return back()->withErrors(['event' => __('events.errors.not_ready_to_publish', [
                'items' => $this->listOf('events.missing_publish', $missing),
            ])]);
        }

        $save->publish($event);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.published')]);

        return GettingStarted::redirect($request, $tenant, to_route('tenants.events.edit', [$tenant, $event]));
    }

    /**
     * Close the specified event.
     */
    public function close(Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        Gate::authorize('close', [$event, $tenant]);

        $save->close($event);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.closed')]);

        return to_route('tenants.events.index', $tenant);
    }

    /**
     * Announce the event on the product site's showcase (opt-in, CLAUDE.md « Annonce sur le
     * site produit »).
     */
    public function announce(Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        Gate::authorize('announce', [$event, $tenant]);

        // La vitrine ne montre que des evenements publies, ouverts, a venir et illustres (decision
        // du 2026-10-07) : le refus nomme ce qui manque.
        if ($missing = $event->missingBeforeAnnouncing()) {
            return back()->withErrors(['event' => __('events.errors.not_ready_to_announce', [
                'items' => $this->listOf('events.missing_announce', $missing),
            ])]);
        }

        $save->announce($event);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.announced')]);

        return to_route('tenants.events.edit', [$tenant, $event]);
    }

    /**
     * Withdraw the event from the showcase.
     */
    public function withdrawAnnouncement(Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        Gate::authorize('announce', [$event, $tenant]);

        $save->withdraw($event);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.announcement_withdrawn')]);

        return to_route('tenants.events.edit', [$tenant, $event]);
    }

    /**
     * Store the event's visual (README ecran 13).
     */
    public function storeVisual(SaveEventVisualRequest $request, Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        $save->visual($event, $request->file('file'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.visual_updated')]);

        return to_route('tenants.events.edit', [$tenant, $event]);
    }

    /**
     * Remove the event's visual.
     */
    public function destroyVisual(Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        Gate::authorize('update', [$event, $tenant]);

        $save->removeVisual($event);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.visual_deleted')]);

        return to_route('tenants.events.edit', [$tenant, $event]);
    }

    /**
     * Duplicate the specified event as a fresh draft.
     */
    public function duplicate(Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        Gate::authorize('duplicate', [$event, $tenant]);

        $copy = $save->duplicate($event);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.duplicated')]);

        return to_route('tenants.events.edit', [$tenant, $copy]);
    }

    /**
     * Delete the specified event.
     */
    public function destroy(Tenant $tenant, Event $event): RedirectResponse
    {
        Gate::authorize('delete', [$event, $tenant]);

        activity()
            ->event('deleted')
            ->withProperties(['old' => ['name' => $event->name]])
            ->log('event.deleted');

        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.deleted')]);

        return to_route('tenants.events.index', $tenant);
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantPayload(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'subdomain' => $tenant->subdomain,
            // Le nom de la carte de vitrine, dans l'apercu de la fiche.
            'displayName' => $tenant->branding->display_name ?? $tenant->name,
            'isReadyToPublish' => $tenant->isReadyToPublish(),
            // Faux jusqu'a la premiere publication : sa confirmation annonce le delai des comptes.
            'paymentDelayActive' => $tenant->paymentAccountDelayApplies(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'subtitle' => $event->subtitle,
            'status' => $event->status->value,
            'statusLabel' => $event->status->label(),
            'startsAt' => $event->starts_at?->toISOString(),
            'venue' => $event->venue,
            'capacity' => $event->capacity(),
            'seatsAtTables' => $event->seatsAtTables(),
            'pricePerPerson' => $event->price_per_person,
            'isPublished' => $event->isPublished(),
            'isReadyToPublish' => $event->isReadyToPublish(),
            'publicUrl' => $event->publicUrl(),
            'isAnnounced' => $event->isAnnounced(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Event $event): array
    {
        return array_merge($this->summary($event), [
            'venueAddress' => $event->venue_address,
            'venueMapUrl' => $event->venue_map_url,
            // Ce qui manque pour publier, puis pour annoncer : la fiche le nomme.
            'missingBeforePublishing' => $event->missingBeforePublishing(),
            'missingBeforeAnnouncing' => $event->missingBeforeAnnouncing(),
            'primaryColor' => $event->primary_color,
            'secondaryColor' => $event->secondary_color,
            'visualUrl' => $event->visualUrl(),
            // La salle telle que le formulaire la decrit : groupes de tables de meme taille.
            'seatsAtTables' => $event->seatsAtTables(),
            'seatingModeLocked' => $event->registrations()->occupyingSeats()->exists(),
            'freeSeats' => $event->seatsAtTables() ? null : $event->capacity(),
            'tableGroups' => SyncSeatingTables::groupsOf($event),
            'priceCategories' => $event->priceCategories()->get()->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'price' => $category->price,
                'quota' => $category->quota,
                // Deja choisi : nom et prix figes, le tarif ne se retire plus.
                'locked' => $category->isChosen(),
            ])->all(),
            'companionLimit' => $event->companion_limit,
            'registrationDeadline' => $event->registration_deadline?->toDateTimeLocalString(),
            'purgeAt' => $event->purge_at?->toDateTimeLocalString(),
            'invitationsSendAt' => $event->invitations_send_at?->toDateTimeLocalString(),
            'holdDurationMinutes' => $event->hold_duration_minutes,
            'startsAtLocal' => $event->starts_at?->toDateTimeLocalString(),
            'paymentAccountIds' => $event->paymentAccounts->pluck('id')->all(),
        ]);
    }

    /**
     * Les evenements de l'organisation proposes comme modeles (« Partir d'un modele », prototype
     * Convive.dc.html), les plus recents d'abord.
     *
     * @return array<int, array{id: int, name: string, tableCount: int, pricePerPerson: int}>
     */
    private function templates(): array
    {
        return Event::withCount('seatingTables')->latest('created_at')->limit(12)->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'tableCount' => (int) $event->seating_tables_count,
                'pricePerPerson' => $event->price_per_person,
            ])
            ->all();
    }

    /**
     * Les valeurs qu'un evenement modele transmet au nouvel evenement, ou null sans modele.
     *
     * Ni le nom ni les dates : ils dependent du nouvel evenement, et des echeances recopiees d'un
     * evenement passe seraient deja depassees. Seuls les comptes de versement encore visibles des
     * invites sont repris : un compte desactive ou dans son delai d'activation n'a pas a revenir.
     *
     * @return array<string, mixed>|null
     */
    private function template(int $sourceId): ?array
    {
        $source = $sourceId > 0 ? Event::with('paymentAccounts')->find($sourceId) : null;

        if ($source === null) {
            return null;
        }

        $visibleAccountIds = PaymentAccount::publiclyVisible()->pluck('id');

        return [
            'sourceId' => $source->id,
            'sourceName' => $source->name,
            'subtitle' => $source->subtitle,
            'venue' => $source->venue,
            'venueAddress' => $source->venue_address,
            'venueMapUrl' => $source->venue_map_url,
            'primaryColor' => $source->primary_color,
            'secondaryColor' => $source->secondary_color,
            'seatsAtTables' => $source->seatsAtTables(),
            'freeSeats' => $source->seatsAtTables() ? null : $source->capacity(),
            'tableGroups' => SyncSeatingTables::groupsOf($source),
            'pricePerPerson' => $source->price_per_person,
            'priceCategories' => $source->priceCategories()->get()->map(fn ($category) => [
                'name' => $category->name,
                'price' => $category->price,
                'quota' => $category->quota,
            ])->all(),
            'companionLimit' => $source->companion_limit,
            'holdDurationMinutes' => $source->hold_duration_minutes,
            'paymentAccountIds' => $source->paymentAccounts->pluck('id')->intersect($visibleAccountIds)->values()->all(),
        ];
    }

    /**
     * Les comptes proposables : ceux qui sont reellement visibles par un invite. Un compte
     * encore dans son delai d'activation n'a rien a faire dans la liste.
     *
     * @return array<int, array<string, mixed>>
     */
    private function paymentAccounts(): array
    {
        return PaymentAccount::publiclyVisible()->ordered()->get()
            ->map(fn (PaymentAccount $account) => [
                'id' => $account->id,
                'label' => $account->label,
                'channelLabel' => $account->channel?->label(),
                'accountNumber' => $account->account_number,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SaveEventRequest $request): array
    {
        return [
            'name' => $request->validated('name'),
            'subtitle' => $request->validated('subtitle'),
            'starts_at' => $request->validated('starts_at'),
            'venue' => $request->validated('venue'),
            'venue_address' => $request->validated('venue_address'),
            'venue_map_url' => $request->validated('venue_map_url'),
            'seats_at_tables' => $request->seatsAtTables(),
            'primary_color' => $request->validated('primary_color'),
            'secondary_color' => $request->validated('secondary_color'),
            'price_per_person' => $request->validated('price_per_person'),
            'companion_limit' => $request->validated('companion_limit') ?? Event::MaximumCompanionLimit,
            'registration_deadline' => $request->validated('registration_deadline'),
            'purge_at' => $request->validated('purge_at'),
            'invitations_send_at' => $request->validated('invitations_send_at'),
            'hold_duration_minutes' => $request->validated('hold_duration_minutes') ?? Event::DefaultHoldDurationMinutes,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function accountIds(SaveEventRequest $request): array
    {
        return array_map('intval', $request->validated('payment_accounts') ?? []);
    }
}
