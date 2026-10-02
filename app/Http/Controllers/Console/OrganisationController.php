<?php

namespace App\Http\Controllers\Console;

use App\Enums\ConsoleArea;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SupportAccessGrant;
use App\Models\SupportAccessRequest;
use App\Models\Tenant;
use App\Support\Console\ConsoleAccess;
use App\Support\Console\OrganisationOverview;
use App\Support\Console\TenantUsageRecorder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Les organisations clientes vues de la console (README ecrans 27 et 28) : des metadonnees, jamais
 * leur contenu. La liste lit la base centrale seule ; la consommation vient des compteurs releves
 * periodiquement, et ceux d'une organisation sont rafraichis quand on ouvre sa fiche.
 */
class OrganisationController extends Controller
{
    /**
     * Display the list of client organisations.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('console/organisations', [
            'isSample' => false,
            // Avec les organisations supprimees par leur Proprietaire : elles restent restaurables
            // jusqu'a leur effacement.
            'organisations' => Tenant::withTrashed()
                ->with('subscription.plan', 'usage', 'suspension', 'limits')
                ->orderBy('name')
                ->get()
                ->map(fn (Tenant $tenant) => OrganisationOverview::summary($tenant))
                ->all(),
            // Chacun decide d'apparaitre ou non dans la liste proposee aux organisations.
            'supportAvailable' => ConsoleAccess::allows($request->user(), ConsoleArea::Support)
                ? $request->user()->support_available
                : null,
            // Les demandes d'aide en attente : une organisation veut ouvrir son espace et personne
            // n'est visible. Lues par les seuls profils qui peuvent recevoir un acces.
            'supportRequests' => ConsoleAccess::allows($request->user(), ConsoleArea::Support)
                ? SupportAccessRequest::query()
                    ->pending()
                    ->whereHas('tenant')
                    ->with('tenant', 'requestedBy', 'takenBy')
                    ->oldest('id')
                    ->get()
                    ->map(fn (SupportAccessRequest $supportRequest) => [
                        'id' => $supportRequest->id,
                        'organisation' => $supportRequest->tenant->name,
                        'requestedBy' => $supportRequest->requestedBy?->name,
                        'reason' => $supportRequest->reason,
                        'requestedAt' => $supportRequest->created_at?->toISOString(),
                        'takenBy' => $supportRequest->takenBy?->name,
                        'takenByMe' => $supportRequest->taken_by_id === $request->user()->id,
                    ])
                    ->all()
                : [],
            // Les acces de support ouverts au compte connecte (README section 3), sa seule porte
            // vers le contenu d'une organisation.
            'supportGrants' => SupportAccessGrant::where('operator_id', $request->user()->id)
                ->active()
                ->with('tenant')
                ->orderBy('expires_at')
                ->get()
                ->map(fn (SupportAccessGrant $grant) => [
                    'id' => $grant->id,
                    'organisation' => $grant->tenant->name,
                    // Ce que l'organisation attend de cet acces : la personne sait quoi regarder.
                    'reason' => $grant->reason,
                    'url' => route('dashboard', ['current_tenant' => $grant->tenant->slug]),
                    'expiresAt' => $grant->expires_at->toISOString(),
                ])
                ->all(),
        ]);
    }

    /**
     * Display one client organisation : metadata only, never its content (README section 3).
     */
    public function show(Request $request, string $organisation): Response
    {
        $tenant = Tenant::withTrashed()->where('slug', $organisation)->first();
        abort_if($tenant === null, 404);

        // Une seule organisation : ses compteurs sont releves a l'ouverture de sa fiche.
        TenantUsageRecorder::refresh($tenant);
        $tenant->load('subscription.plan', 'usage', 'suspension', 'branding', 'limits');

        return Inertia::render('console/organisation', [
            'isSample' => false,
            'organisation' => OrganisationOverview::details($tenant),
            'plans' => collect(PlanCode::cases())
                ->map(fn (PlanCode $code) => Plan::ensure($code))
                ->map(fn (Plan $plan) => ['code' => $plan->code, 'name' => $plan->name])
                ->all(),
            // Les actions sont reservees aux profils qui en ont la zone ; chaque route revalide.
            'canAct' => ConsoleAccess::allows($request->user(), ConsoleArea::OrganisationActions),
        ]);
    }
}
