<?php

namespace App\Http\Controllers\Events;

use App\Actions\Registrations\CancelRegistration;
use App\Actions\Registrations\PurgeRegistrations;
use App\Enums\RegistrationStatus;
use App\Exports\RegistrationsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\CancelRegistrationRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Support\PdfLetterhead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La base d'inscrits (README ecran 20), etape 9 de « Ordre de construction ».
 */
class RegistrationController extends Controller
{
    /**
     * Display the paginated, filterable, searchable list of registrations for the given event.
     *
     * La meme requete (`filteredQuery`) sert la liste affichee et chaque export : le filtre a
     * l'ecran doit toujours etre celui qu'un export produit, jamais un ecart entre les deux.
     */
    public function index(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('viewAny', [Registration::class, $tenant]);

        $registrations = $this->filteredQuery($request, $event)
            ->with(['unit', 'tableAssignment.seatingTable'])
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('events/registrations', [
            'tenant' => ['slug' => $tenant->slug],
            'event' => ['id' => $event->id, 'name' => $event->name],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'rows' => $registrations->getCollection()->map(fn (Registration $registration) => $this->row($registration)),
            'meta' => [
                'currentPage' => $registrations->currentPage(),
                'lastPage' => $registrations->lastPage(),
                'total' => $registrations->total(),
            ],
            'filters' => [
                'search' => $request->string('filter.search')->toString() ?: null,
                'status' => $request->string('filter.status')->toString() ?: null,
            ],
        ]);
    }

    /**
     * Cancel the given registration : an organisation decision, distinct from the guest-side
     * lifecycle failures (README 2.1, `RegistrationStatus::Cancelled`).
     */
    public function cancel(CancelRegistrationRequest $request, Tenant $tenant, Event $event, Registration $registration, CancelRegistration $cancel): RedirectResponse
    {
        $cancel->handle($registration, $request->validated('reason'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('registrations.flash.cancelled')]);

        return to_route('tenants.events.registrations.index', [$tenant, $event]);
    }

    /**
     * Purge the event's unfinalized registrations on demand (README 2.4), reusing the same
     * action as the scheduled purge (etape 5) : one rule, one implementation.
     */
    public function purge(Tenant $tenant, Event $event, PurgeRegistrations $purge): RedirectResponse
    {
        Gate::authorize('purge', [Registration::class, $tenant]);

        $result = $purge->handle($event);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('registrations.flash.purged', $result['count'], ['count' => $result['count']]),
        ]);

        return to_route('tenants.events.registrations.index', [$tenant, $event]);
    }

    /**
     * Export the filtered registrations to an Excel workbook (README ecran 20).
     */
    public function exportExcel(Request $request, Tenant $tenant, Event $event): BinaryFileResponse
    {
        Gate::authorize('export', [Registration::class, $tenant]);

        return Excel::download(
            new RegistrationsExport($this->exportQuery($request, $event)),
            "inscrits-{$event->id}.xlsx",
        );
    }

    /**
     * Export the filtered registrations to a CSV file (README ecran 20).
     */
    public function exportCsv(Request $request, Tenant $tenant, Event $event): BinaryFileResponse
    {
        Gate::authorize('export', [Registration::class, $tenant]);

        return Excel::download(
            new RegistrationsExport($this->exportQuery($request, $event)),
            "inscrits-{$event->id}.csv",
            ExcelFormat::CSV,
        );
    }

    /**
     * Export the filtered registrations to a PDF document (README ecran 20), with the
     * organisation's letterhead, stamp and signature.
     */
    public function exportPdf(Request $request, Tenant $tenant, Event $event): PdfBuilder
    {
        Gate::authorize('export', [Registration::class, $tenant]);

        $registrations = $this->exportQuery($request, $event)->orderBy('id')->get();

        return Pdf::view('pdf.registrations', [
            'letterhead' => PdfLetterhead::for($tenant),
            'event' => ['name' => $event->name, 'startsAt' => $event->starts_at],
            'registrations' => $registrations->map(fn (Registration $registration) => [
                'name' => $registration->name,
                'phone' => $registration->phone,
                'unit' => $registration->unit->name,
                'partySize' => $registration->party_size,
                'amountDue' => $registration->amount_due,
                'statusLabel' => $registration->status->label(),
                'tableNumber' => $registration->tableAssignment?->seatingTable->number,
            ])->all(),
            'generatedAt' => now(),
        ])
            ->format('a4')
            ->landscape()
            ->download("inscrits-{$event->id}.pdf");
    }

    /**
     * Export the entrance checklists, one section per table (README ecrans 15 et 20).
     *
     * Independantes du filtre de l'ecran : une liste de controle est toujours la meme, les
     * inscriptions confirmees et placees de l'evenement. Une inscription non placee n'y figure
     * pas, il n'y a rien a cocher a l'entree tant qu'elle n'a pas de table.
     */
    public function exportChecklists(Tenant $tenant, Event $event): PdfBuilder
    {
        Gate::authorize('export', [Registration::class, $tenant]);

        $tables = SeatingTable::where('event_id', $event->id)
            ->ordered()
            ->with('assignments.registration.unit', 'assignments.registration.companions')
            ->get()
            ->map(function (SeatingTable $table) {
                $registrations = $table->assignments
                    ->map(fn ($assignment) => $assignment->registration)
                    ->filter(fn (Registration $registration) => $registration->status === RegistrationStatus::Confirmed)
                    ->sortBy('name')
                    ->values();

                return [
                    'number' => $table->number,
                    'capacity' => $table->capacity,
                    'seats' => (int) $registrations->sum('party_size'),
                    'registrations' => $registrations->map(fn (Registration $registration) => [
                        'name' => $registration->name,
                        'unit' => $registration->unit->name,
                        'partySize' => $registration->party_size,
                        'companions' => $registration->companions->pluck('name')->all(),
                    ])->all(),
                ];
            })
            ->filter(fn (array $table) => $table['registrations'] !== [])
            ->values()
            ->all();

        return Pdf::view('pdf.checklists', [
            'letterhead' => PdfLetterhead::for($tenant),
            'event' => ['name' => $event->name, 'startsAt' => $event->starts_at],
            'tables' => $tables,
            'generatedAt' => now(),
        ])
            ->format('a4')
            ->download("listes-de-controle-{$event->id}.pdf");
    }

    /**
     * @return Builder<Registration>
     */
    private function exportQuery(Request $request, Event $event): Builder
    {
        return $this->filteredQuery($request, $event)
            ->getEloquentBuilder()
            ->with(['unit', 'tableAssignment.seatingTable']);
    }

    /**
     * Build the filtered, searched, sorted query shared by the list and every export.
     *
     * `without_proof` reutilise `Registration::UnfinalizedStatuses` tel quel : « sans preuve » a
     * l'ecran et « non finalise » pour la purge (README 2.4) designent le meme ensemble de
     * statuts, pas la peine d'une seconde constante.
     *
     * @return QueryBuilder<Registration>
     */
    private function filteredQuery(Request $request, Event $event): QueryBuilder
    {
        return QueryBuilder::for(Registration::where('event_id', $event->id))
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, string $value) {
                    $query->where(function (Builder $query) use ($value) {
                        $query->where('name', 'like', "%{$value}%")
                            ->orWhere('phone', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('status', function (Builder $query, string $value) {
                    match ($value) {
                        'confirmed' => $query->where('status', RegistrationStatus::Confirmed),
                        'proof_submitted' => $query->where('status', RegistrationStatus::ProofSubmitted),
                        'without_proof' => $query->whereIn('status', Registration::UnfinalizedStatuses),
                        'cancelled' => $query->where('status', RegistrationStatus::Cancelled),
                        default => null,
                    };
                }),
            )
            ->defaultSort('-created_at')
            ->allowedSorts('name', 'created_at', 'amount_due', 'party_size');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Registration $registration): array
    {
        return [
            'id' => $registration->id,
            'name' => $registration->name,
            'phone' => $registration->phone,
            'unit' => $registration->unit->name,
            'partySize' => $registration->party_size,
            'amountDue' => $registration->amount_due,
            'status' => $registration->status->value,
            'statusLabel' => $registration->status->label(),
            'tableNumber' => $registration->tableAssignment?->seatingTable->number,
            'cancellationReason' => $registration->cancellation_reason,
        ];
    }
}
