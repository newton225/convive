<?php

namespace App\Http\Controllers\Events;

use App\Actions\Seating\MoveRegistrationToTable;
use App\Actions\Seating\SaveUnitSeparationRule;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\AssignSeatingTableRequest;
use App\Http\Requests\Events\StoreUnitSeparationRuleRequest;
use App\Http\Requests\Events\UpdateSeatingTableRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationTableAssignment;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitSeparationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le plan de salle (README ecran 21), etape 6 de « Ordre de construction ». L'attribution
 * automatique se joue a la validation d'une preuve (`App\Actions\Seating\AssignTable`) ; cet
 * ecran couvre ce que README 2.6 exige en plus : « un placement manuel par l'organisateur doit
 * rester possible et tracee ».
 */
class SeatingController extends Controller
{
    /**
     * Display the tables of the event, and the confirmed registrations still unseated.
     */
    public function index(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('viewAny', [SeatingTable::class, $tenant]);

        // Un evenement sans table n'a pas de plan de salle (decision du 2026-10-08).
        abort_unless($event->seatsAtTables(), 404);

        $tables = SeatingTable::where('event_id', $event->id)
            ->with(['reservedUnit', 'assignments.registration.unit'])
            ->ordered()
            ->get();

        $unseated = Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::Confirmed)
            ->whereDoesntHave('tableAssignment')
            ->with('unit')
            ->orderBy('name')
            ->get();

        $constraints = UnitSeparationRule::where('event_id', $event->id)
            ->with(['unitA', 'unitB'])
            ->get();

        return Inertia::render('events/seating', [
            'tenant' => ['slug' => $tenant->slug],
            'event' => ['id' => $event->id, 'name' => $event->name],
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'tables' => $tables->map(fn (SeatingTable $table) => $this->tableRow($table))->all(),
            'unseated' => $unseated->map(fn (Registration $registration) => $this->registrationRow($registration))->all(),
            'units' => Unit::active()->ordered()->get()
                ->map(fn (Unit $unit) => ['id' => $unit->id, 'name' => $unit->name])
                ->all(),
            'constraints' => $constraints->map(fn (UnitSeparationRule $rule) => [
                'id' => $rule->id,
                'unitA' => $rule->unitA->name,
                'unitB' => $rule->unitB->name,
            ])->all(),
        ]);
    }

    /**
     * Add a separation rule between two units, for this event.
     */
    public function storeConstraint(
        StoreUnitSeparationRuleRequest $request,
        Tenant $tenant,
        Event $event,
        SaveUnitSeparationRule $save,
    ): RedirectResponse {
        // Un evenement sans table n'a pas de plan de salle (decision du 2026-10-08).
        abort_unless($event->seatsAtTables(), 404);

        $save->store($event, (int) $request->validated('unit_id'), (int) $request->validated('other_unit_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('seating.flash.constraint_added')]);

        return to_route('tenants.events.seating.index', [$tenant, $event]);
    }

    /**
     * Remove a separation rule.
     */
    public function destroyConstraint(
        Tenant $tenant,
        Event $event,
        UnitSeparationRule $constraint,
        SaveUnitSeparationRule $save,
    ): RedirectResponse {
        Gate::authorize('assign', [SeatingTable::class, $tenant]);

        // Un evenement sans table n'a pas de plan de salle (decision du 2026-10-08).
        abort_unless($event->seatsAtTables(), 404);

        abort_unless($constraint->event_id === $event->id, 404);

        $save->delete($constraint);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('seating.flash.constraint_removed')]);

        return to_route('tenants.events.seating.index', [$tenant, $event]);
    }

    /**
     * Move the given registration to the submitted table, or off any table when none is given.
     */
    public function assign(
        AssignSeatingTableRequest $request,
        Tenant $tenant,
        Event $event,
        Registration $registration,
        MoveRegistrationToTable $move,
    ): RedirectResponse {
        // Un evenement sans table n'a pas de plan de salle (decision du 2026-10-08).
        abort_unless($event->seatsAtTables(), 404);

        $tableId = $request->validated('seating_table_id');
        $table = $tableId !== null ? SeatingTable::query()->whereKey($tableId)->firstOrFail() : null;

        $move->handle($registration, $table, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($table !== null ? 'seating.flash.moved' : 'seating.flash.removed'),
        ]);

        return to_route('tenants.events.seating.index', [$tenant, $event]);
    }

    /**
     * Change how many seats one table has (decision du 2026-09-29 : les tables n'ont pas toutes
     * la meme taille). Les garde-fous sont dans `UpdateSeatingTableRequest`.
     */
    public function updateTable(UpdateSeatingTableRequest $request, Tenant $tenant, Event $event, SeatingTable $table): RedirectResponse
    {
        // Un evenement sans table n'a pas de plan de salle (decision du 2026-10-08).
        abort_unless($event->seatsAtTables(), 404);

        // `SeatingTable` vit dans la base du locataire, mais peut appartenir a un autre de ses
        // evenements : meme reponse qu'un objet introuvable.
        abort_if($table->event_id !== $event->id, 404);

        $before = $table->capacity;
        $table->update(['capacity' => $request->integer('capacity')]);

        activity()
            ->performedOn($event)
            ->event('updated')
            ->withProperties([
                'old' => ['table' => $table->number, 'capacity' => $before],
                'attributes' => ['table' => $table->number, 'capacity' => $table->capacity],
            ])
            ->log('seating.table_resized');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('seating.capacity.flash', [
            'number' => $table->number,
            'count' => $table->capacity,
        ])]);

        return to_route('tenants.events.seating.index', [$tenant, $event]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableRow(SeatingTable $table): array
    {
        return [
            'id' => $table->id,
            'number' => $table->number,
            'capacity' => $table->capacity,
            'remaining' => $table->remainingCapacity(),
            'reservedUnit' => $table->reservedUnit?->name,
            'occupants' => $table->assignments->map(fn (RegistrationTableAssignment $assignment) => $this->registrationRow($assignment->registration))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationRow(Registration $registration): array
    {
        return [
            'id' => $registration->id,
            'name' => $registration->name,
            'unit' => $registration->unit->name,
            'partySize' => $registration->party_size,
        ];
    }
}
