<?php

namespace App\Http\Controllers\Events;

use App\Actions\Events\SaveEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\SaveEventRequest;
use App\Http\Requests\Events\SaveEventVisualRequest;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Support\PlanLimits;
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

        return Inertia::render('events/index', [
            'tenant' => $this->tenantPayload($tenant),
            'events' => Event::ordered()->with('paymentAccounts')->get()
                ->map(fn (Event $event) => $this->summary($event)),
            'permissions' => $request->user()->toTenantPermissions($tenant),
        ]);
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
                'holdDurationMinutes' => Event::DefaultHoldDurationMinutes,
            ],
            'tenantColors' => $tenant->brandingOrCreate()->colors(),
        ]);
    }

    /**
     * Store a newly created event.
     */
    public function store(SaveEventRequest $request, Tenant $tenant, SaveEvent $save): RedirectResponse
    {
        $event = $save->handle(null, $this->attributes($request), $this->accountIds($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.created')]);

        return to_route('tenants.events.edit', [$tenant, $event]);
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
                'holdDurationMinutes' => Event::DefaultHoldDurationMinutes,
            ],
            'tenantColors' => $tenant->brandingOrCreate()->colors(),
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(SaveEventRequest $request, Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        $save->handle($event, $this->attributes($request), $this->accountIds($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.updated')]);

        return to_route('tenants.events.edit', [$tenant, $event]);
    }

    /**
     * Hand out the public link of the event.
     */
    public function publish(Tenant $tenant, Event $event, SaveEvent $save): RedirectResponse
    {
        Gate::authorize('publish', [$event, $tenant]);

        // Avant la garde « pret a publier » : un espace au plafond de son plan n'a pas a
        // completer son dossier pour apprendre qu'il ne peut pas publier.
        if (! PlanLimits::for($tenant)->canPublishEvent()) {
            return back()->withErrors(['event' => __('billing.errors.event_quota', ['plan' => $tenant->plan()->name])]);
        }

        if (! $event->isReadyToPublish()) {
            return back()->withErrors(['event' => __('events.errors.not_ready_to_publish')]);
        }

        $save->publish($event);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('events.flash.published')]);

        return to_route('tenants.events.edit', [$tenant, $event]);
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
            'isReadyToPublish' => $tenant->isReadyToPublish(),
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
            'pricePerPerson' => $event->price_per_person,
            'isPublished' => $event->isPublished(),
            'isReadyToPublish' => $event->isReadyToPublish(),
            'publicUrl' => $event->publicUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function details(Event $event): array
    {
        return array_merge($this->summary($event), [
            'venueAddress' => $event->venue_address,
            'primaryColor' => $event->primary_color,
            'secondaryColor' => $event->secondary_color,
            'visualUrl' => $event->visualUrl(),
            'tableCount' => $event->table_count,
            'seatsPerTable' => $event->seats_per_table,
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
            'primary_color' => $request->validated('primary_color'),
            'secondary_color' => $request->validated('secondary_color'),
            'table_count' => $request->validated('table_count') ?? 0,
            'seats_per_table' => $request->validated('seats_per_table') ?? 0,
            'price_per_person' => $request->validated('price_per_person') ?? 0,
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
