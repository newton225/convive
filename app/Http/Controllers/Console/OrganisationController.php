<?php

namespace App\Http\Controllers\Console;

use App\Enums\ConsoleArea;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SupportAccessGrant;
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
            'organisations' => Tenant::query()
                ->with('subscription.plan', 'usage', 'suspension')
                ->orderBy('name')
                ->get()
                ->map(fn (Tenant $tenant) => OrganisationOverview::summary($tenant))
                ->all(),
            // Chacun decide d'apparaitre ou non dans la liste proposee aux organisations.
            'supportAvailable' => ConsoleAccess::allows($request->user(), ConsoleArea::Support)
                ? $request->user()->support_available
                : null,
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
        $tenant = Tenant::where('slug', $organisation)->first();
        abort_if($tenant === null, 404);

        // Une seule organisation : ses compteurs sont releves a l'ouverture de sa fiche.
        TenantUsageRecorder::refresh($tenant);
        $tenant->load('subscription.plan', 'usage', 'suspension', 'branding');

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
