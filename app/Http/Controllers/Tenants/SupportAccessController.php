<?php

namespace App\Http\Controllers\Tenants;

use App\Actions\Tenants\ManageSupportAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\OpenSupportAccessRequest;
use App\Models\SupportAccessGrant;
use App\Models\SupportAccessView;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'acces du support (README ecran 25 et section 3) : un Proprietaire ouvre a un membre nomme de
 * l'equipe Convive un acces en lecture seule, de 24 heures au plus, qu'il peut revoquer, et relit
 * ce qui a ete consulte.
 *
 * L'equipe Convive est, pour l'instant, celle du controle d'acces actuel de la console
 * (`convive.console.operators`) : les comptes editeur distincts arrivent avec le reste de la
 * console (etape 11) et remplaceront `operators()`.
 */
class SupportAccessController extends Controller
{
    /**
     * Nombre de pages consultees et d'acces passes relus a l'ecran.
     */
    private const ViewsShown = 50;

    private const PastAccessesShown = 20;

    /**
     * Display the support access of the organisation : the one in progress, and the past ones.
     */
    public function show(Tenant $tenant): Response
    {
        Gate::authorize('manageSupportAccess', $tenant);

        $active = SupportAccessGrant::where('tenant_id', $tenant->id)
            ->active()
            ->with('operator', 'grantedBy')
            ->latest('id')
            ->first();

        return Inertia::render('tenants/support-access', [
            'tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name],
            'durations' => SupportAccessGrant::DurationsInHours,
            'operators' => self::operators()
                ->map(fn (User $operator) => ['id' => $operator->id, 'name' => $operator->name])
                ->values()
                ->all(),
            'activeAccess' => $active === null ? null : [
                'id' => $active->id,
                'operator' => $active->operator->name,
                'grantedBy' => $active->grantedBy?->name,
                'grantedAt' => $active->created_at?->toISOString(),
                'expiresAt' => $active->expires_at->toISOString(),
                'views' => $active->views()
                    ->latest('viewed_at')
                    ->limit(self::ViewsShown)
                    ->get()
                    ->map(fn (SupportAccessView $view) => [
                        'id' => $view->id,
                        'at' => $view->viewed_at->toISOString(),
                        'page' => $view->page,
                    ])
                    ->all(),
            ],
            'pastAccesses' => SupportAccessGrant::where('tenant_id', $tenant->id)
                ->when($active, fn ($query) => $query->whereKeyNot($active->id))
                ->with('operator')
                ->withCount('views')
                ->latest('id')
                ->limit(self::PastAccessesShown)
                ->get()
                ->map(fn (SupportAccessGrant $grant) => [
                    'id' => $grant->id,
                    'operator' => $grant->operator->name,
                    'grantedAt' => $grant->created_at?->toISOString(),
                    'endedAt' => $grant->endedAt()->toISOString(),
                    'endReason' => $grant->revoked_at !== null ? 'revoked' : 'expired',
                    'viewsCount' => $grant->views_count,
                ])
                ->all(),
        ]);
    }

    /**
     * Open a read-only access to one member of the Convive team.
     */
    public function store(OpenSupportAccessRequest $request, Tenant $tenant, ManageSupportAccess $manage): RedirectResponse
    {
        $operator = self::operators()->firstWhere('id', (int) $request->validated('operator_id'));
        abort_if($operator === null, 404);

        $manage->open($tenant, $operator, $request->user(), (int) $request->validated('duration'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('support_access.flash.opened', ['operator' => $operator->name])]);

        return to_route('tenants.support-access.show', $tenant);
    }

    /**
     * Revoke the given access before its term.
     */
    public function destroy(Request $request, Tenant $tenant, SupportAccessGrant $grant, ManageSupportAccess $manage): RedirectResponse
    {
        Gate::authorize('manageSupportAccess', $tenant);

        // Table centrale : l'acces d'une autre organisation n'existe pas pour celle-ci.
        abort_if($grant->tenant_id !== $tenant->id, 404);

        $manage->revoke($grant, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('support_access.flash.revoked')]);

        return to_route('tenants.support-access.show', $tenant);
    }

    /**
     * Get the members of the Convive team an access can be opened to.
     *
     * @return Collection<int, User>
     */
    public static function operators(): Collection
    {
        $emails = config('convive.console.operators');

        return $emails === []
            ? new Collection
            : User::whereIn('email', $emails)->orderBy('name')->get();
    }
}
