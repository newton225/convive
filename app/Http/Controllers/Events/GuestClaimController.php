<?php

namespace App\Http\Controllers\Events;

use App\Actions\Claims\ResolveGuestClaim;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ResolveGuestClaimRequest;
use App\Models\Event;
use App\Models\GuestClaim;
use App\Models\Tenant;
use App\Support\ListPage;
use App\Support\PhoneNumber;
use App\Support\Search\UnaccentedSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Les reclamations des invites d'un evenement (decision du 2026-10-09). Vit sous l'evenement, comme
 * les preuves : une reclamation n'a de sens que rattachee au dossier d'un evenement precis.
 */
class GuestClaimController extends Controller
{
    private const StatusFilters = ['open', 'resolved', 'all'];

    /**
     * Display the claims of the given event, the open ones first.
     */
    public function index(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('viewAny', [GuestClaim::class, $tenant]);

        $search = trim((string) $request->input('filter.search', ''));
        $status = in_array($request->input('filter.status'), self::StatusFilters, true) ? $request->input('filter.status') : 'open';

        $query = GuestClaim::query()
            ->whereHas('registration', fn (Builder $registration) => $registration->where('event_id', $event->id))
            ->with('registration')
            ->when($status !== 'all', fn (Builder $claims) => $claims->where('status', $status))
            ->when($search !== '', fn (Builder $claims) => $claims->whereHas('registration', fn (Builder $registration) => $registration
                ->where(fn (Builder $any) => UnaccentedSearch::apply($any, ['name', 'reference', 'phone'], $search))))
            ->latest('created_at')
            ->orderByDesc('id');

        $page = ListPage::of($query, $request)->through(fn (GuestClaim $claim) => [
            'id' => $claim->id,
            'category' => $claim->category->value,
            'message' => $claim->message,
            'status' => $claim->status->value,
            'createdAt' => $claim->created_at?->toISOString(),
            'resolvedAt' => $claim->resolved_at?->toISOString(),
            'name' => $claim->registration->name,
            'reference' => $claim->registration->reference,
            'phone' => PhoneNumber::display($claim->registration->phone),
            'registrationStatus' => $claim->registration->status->value,
        ]);

        return Inertia::render('events/claims', [
            'tenant' => ['slug' => $tenant->slug],
            'event' => ['id' => $event->id, 'name' => $event->name],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'rows' => collect($page->items())->values(),
            'meta' => ListPage::meta($page),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Mark the claim as handled. Une reclamation d'un autre evenement que celui de l'adresse recoit
     * un 404 : c'est ce qui tient la limite d'un acces du support a un evenement.
     */
    public function resolve(ResolveGuestClaimRequest $request, Tenant $tenant, Event $event, GuestClaim $claim, ResolveGuestClaim $resolve): RedirectResponse
    {
        abort_unless($claim->registration->event_id === $event->id, 404);

        $resolve->handle($claim, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('claims.flash.resolved')]);

        return back();
    }
}
