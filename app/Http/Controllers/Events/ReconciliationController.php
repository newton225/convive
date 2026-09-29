<?php

namespace App\Http\Controllers\Events;

use App\Actions\Reconciliation\ImportReconciliationStatement;
use App\Actions\Reconciliation\ResolveStatementLine;
use App\Enums\ReconciliationOutcome;
use App\Enums\RegistrationStatus;
use App\Enums\TenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ImportStatementRequest;
use App\Http\Requests\Events\ResolveStatementLineRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\StatementImport;
use App\Models\StatementLine;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Le rapprochement du releve (README ecran 19), etape 9 de « Ordre de construction ».
 *
 * L'ecran montre les lignes d'un seul import a la fois, le dernier par defaut : un flux cumulatif
 * de tous les imports melangerait des releves qui n'ont pas la meme periode.
 */
class ReconciliationController extends Controller
{
    /**
     * Display the lines of the selected statement, with the count of each outcome.
     */
    public function index(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('viewAny', [StatementImport::class, $tenant]);

        $imports = StatementImport::where('event_id', $event->id)->orderByDesc('id')->get();

        $current = $request->has('import')
            ? StatementImport::where('event_id', $event->id)->findOrFail($request->integer('import'))
            : $imports->first();

        $lines = $current === null
            ? null
            : QueryBuilder::for(StatementLine::where('statement_import_id', $current->id))
                ->allowedFilters(
                    AllowedFilter::exact('outcome'),
                    AllowedFilter::callback('search', function (Builder $query, string $value) {
                        $query->where(function (Builder $query) use ($value) {
                            $query->where('reference', 'like', "%{$value}%")
                                ->orWhere('issuer', 'like', "%{$value}%");
                        });
                    }),
                )
                ->defaultSort('line_number')
                ->allowedSorts('line_number', 'occurred_on', 'issuer', 'amount')
                ->with('matchedRegistration')
                ->paginate(25)
                ->withQueryString();

        $totals = $current === null
            ? collect()
            : StatementLine::where('statement_import_id', $current->id)
                ->selectRaw('outcome, count(*) as total')
                ->groupBy('outcome')
                ->pluck('total', 'outcome');

        // Ne charge la liste des inscriptions que pour qui peut s'en servir ; la resolution
        // elle-meme reste autorisee par `StatementLinePolicy`.
        $canResolve = $request->user()->hasTenantPermission($tenant, TenantPermission::ReconciliationResolve);

        return Inertia::render('events/reconciliation', [
            'tenant' => ['slug' => $tenant->slug],
            'event' => ['id' => $event->id, 'name' => $event->name],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'imports' => $imports->map(fn (StatementImport $import) => [
                'id' => $import->id,
                'filename' => $import->original_filename,
                'rowCount' => $import->row_count,
                'importedAt' => $import->created_at?->toISOString(),
            ]),
            'currentImportId' => $current?->id,
            'stats' => [
                'matched' => (int) ($totals[ReconciliationOutcome::Matched->value] ?? 0),
                'amountMismatch' => (int) ($totals[ReconciliationOutcome::AmountMismatch->value] ?? 0),
                'approximateName' => (int) ($totals[ReconciliationOutcome::ApproximateName->value] ?? 0),
                'noRegistration' => (int) ($totals[ReconciliationOutcome::NoRegistration->value] ?? 0),
            ],
            'rows' => $lines?->getCollection()->map(fn (StatementLine $line) => $this->row($line))->values() ?? [],
            'meta' => [
                'currentPage' => $lines?->currentPage() ?? 1,
                'lastPage' => $lines?->lastPage() ?? 1,
                'total' => $lines?->total() ?? 0,
            ],
            'registrationOptions' => $canResolve ? $this->registrationOptions($event) : [],
            'filters' => [
                'search' => $request->string('filter.search')->toString() ?: null,
                'outcome' => $request->string('filter.outcome')->toString() ?: null,
                'sort' => $request->string('sort')->toString() ?: null,
            ],
        ]);
    }

    /**
     * Import a statement and match its lines. A malformed statement is rejected whole, with the
     * offending line number on the `file` field (see `ImportReconciliationStatement`).
     */
    public function import(ImportStatementRequest $request, Tenant $tenant, Event $event, ImportReconciliationStatement $import): RedirectResponse
    {
        $import->handle($event, $request->file('file'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('reconciliation.flash.imported')]);

        return to_route('tenants.events.reconciliation.index', [$tenant, $event]);
    }

    /**
     * Resolve a line by hand : match it to a registration, or mark it as seen without one.
     */
    public function resolve(ResolveStatementLineRequest $request, Tenant $tenant, Event $event, StatementLine $line, ResolveStatementLine $resolve): RedirectResponse
    {
        $resolve->handle(
            $line,
            $request->filled('registration_id') ? Registration::findOrFail($request->integer('registration_id')) : null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('reconciliation.flash.resolved')]);

        return back();
    }

    /**
     * Get the registrations a line can be matched to by hand : those carrying a payment, pending
     * verification or already confirmed.
     *
     * @return array<int, array{id: int, name: string, amountDue: int}>
     */
    private function registrationOptions(Event $event): array
    {
        return Registration::where('event_id', $event->id)
            ->whereIn('status', [RegistrationStatus::ProofSubmitted, RegistrationStatus::Confirmed])
            ->orderBy('name')
            ->get(['id', 'name', 'amount_due'])
            ->map(fn (Registration $registration) => [
                'id' => $registration->id,
                'name' => $registration->name,
                'amountDue' => $registration->amount_due,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(StatementLine $line): array
    {
        return [
            'id' => $line->id,
            'lineNumber' => $line->line_number,
            'occurredOn' => $line->occurred_on->toDateString(),
            'reference' => $line->reference,
            'issuer' => $line->issuer,
            'amount' => $line->amount,
            'outcome' => $line->outcome->value,
            'outcomeLabel' => $line->outcome->label(),
            'matchedRegistration' => $line->matchedRegistration === null ? null : [
                'id' => $line->matchedRegistration->id,
                'name' => $line->matchedRegistration->name,
            ],
            'resolved' => $line->resolved_at !== null,
        ];
    }
}
