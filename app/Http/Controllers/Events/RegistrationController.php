<?php

namespace App\Http\Controllers\Events;

use App\Actions\Notifications\SendAlert;
use App\Actions\Registrations\CancelRegistration;
use App\Actions\Registrations\PurgeRegistrations;
use App\Enums\NotificationType;
use App\Enums\RegistrationStatus;
use App\Exports\RegistrationsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\CancelRegistrationRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PdfLetterhead;
use Carbon\CarbonInterface;
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
            ->with(['unit', 'tableAssignment.seatingTable', 'latestProof', 'tickets.arrival'])
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
                'sort' => $request->string('sort')->toString() ?: null,
            ],
            'cancellations' => $this->recentCancellations($event),
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

        $this->journalExport($request, $event, 'xlsx', $this->exportQuery($request, $event)->count());

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

        $this->journalExport($request, $event, 'csv', $this->exportQuery($request, $event)->count());

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

        $this->journalExport($request, $event, 'pdf', $registrations->count());

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
            'watermark' => $this->watermark($request),
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
    public function exportChecklists(Request $request, Tenant $tenant, Event $event): PdfBuilder
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

        $this->journalExport($request, $event, 'checklists', (int) collect($tables)->sum(fn (array $table) => count($table['registrations'])));

        return Pdf::view('pdf.checklists', [
            'letterhead' => PdfLetterhead::for($tenant),
            'event' => ['name' => $event->name, 'startsAt' => $event->starts_at],
            'tables' => $tables,
            'generatedAt' => now(),
            'watermark' => $this->watermark($request),
        ])
            ->format('a4')
            ->download("listes-de-controle-{$event->id}.pdf");
    }

    /**
     * Journalise un export de la base d'inscrits (SECURITY.md M3) : qui a extrait combien de
     * lignes, avec quels filtres. Un export autorise n'est pas une intrusion, mais c'est une
     * fuite possible, et l'entree est le seul moyen de la reconstituer.
     */
    private function journalExport(Request $request, Event $event, string $format, int $rows): void
    {
        activity()
            ->performedOn($event)
            ->event('exported')
            ->withProperties([
                'format' => $format,
                'rows' => $rows,
                'filters' => $request->query('filter', []),
            ])
            ->log('registrations.exported');

        if ($rows >= (int) config('convive.exports.alert_rows')) {
            app(SendAlert::class)->toTenantMembers(
                NotificationType::LargeExport,
                ['name' => $request->user()->name, 'count' => $rows, 'event' => $event->name, 'format' => $format],
                route('tenants.audit.index', Tenant::current(), absolute: false),
                except: $request->user(),
            );
        }
    }

    /**
     * Identity stamped across every page of an exported PDF (SECURITY.md M3) : a printed or
     * forwarded copy still says who extracted it, and when.
     *
     * @return array{name: string, at: CarbonInterface}
     */
    private function watermark(Request $request): array
    {
        return ['name' => $request->user()->name, 'at' => now()];
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
                            ->orWhere('reference', 'like', "%{$value}%")
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
        $arrivals = $registration->tickets->pluck('arrival')->filter();

        return [
            'id' => $registration->id,
            'reference' => $registration->reference,
            'name' => $registration->name,
            'phone' => $registration->phone,
            'unit' => $registration->unit->name,
            'partySize' => $registration->party_size,
            'amountDue' => $registration->amount_due,
            'status' => $registration->status->value,
            'statusLabel' => $registration->status->label(),
            'tableNumber' => $registration->tableAssignment?->seatingTable->number,
            'cancellationReason' => $registration->cancellation_reason,
            'channelLabel' => $registration->latestProof?->channel->label(),
            // Un billet par personne (README 2.8, ecran 20) : combien du groupe sont entres, et quand
            // le premier a passe la porte.
            'enteredCount' => $arrivals->count(),
            'enteredAt' => $arrivals->min('created_at')?->toISOString(),
        ];
    }

    /**
     * Les dernieres annulations de l'evenement, avec motif, auteur et date : le prototype les tient
     * a part de la liste, pour qu'une annulation reste lisible sans filtrer.
     *
     * @return array<int, array{name: string, reason: string|null, cancelledAt: string|null, cancelledBy: string|null}>
     */
    private function recentCancellations(Event $event): array
    {
        $cancelled = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::Cancelled)
            ->latest('cancelled_at')
            ->limit(10)
            ->get(['name', 'cancellation_reason', 'cancelled_at', 'cancelled_by_user_id']);

        // L'auteur vit dans la base centrale (`users`) : une seule requete pour toute la liste.
        $authors = User::query()
            ->whereIn('id', $cancelled->pluck('cancelled_by_user_id')->filter()->unique())
            ->pluck('name', 'id');

        return $cancelled->map(fn (Registration $registration) => [
            'name' => $registration->name,
            'reason' => $registration->cancellation_reason,
            'cancelledAt' => $registration->cancelled_at?->toISOString(),
            'cancelledBy' => $registration->cancelled_by_user_id !== null
                ? $authors->get($registration->cancelled_by_user_id)
                : null,
        ])->all();
    }
}
