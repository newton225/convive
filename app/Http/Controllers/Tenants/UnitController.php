<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\SaveUnitRequest;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    /**
     * Display the units of the tenant.
     */
    public function index(Request $request, Tenant $tenant): Response
    {
        Gate::authorize('viewAny', [Unit::class, $tenant]);

        return Inertia::render('tenants/units', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'units' => Unit::ordered()->get()->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'position' => $unit->position,
                'isActive' => $unit->is_active,
                'isNone' => $unit->isNone(),
            ]),
        ]);
    }

    /**
     * Store a newly created unit.
     */
    public function store(SaveUnitRequest $request, Tenant $tenant): RedirectResponse
    {
        $unit = new Unit([
            'name' => $request->validated('name'),
            'position' => $request->validated('position') ?? $this->nextPosition(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $unit->save();

        activity()
            ->performedOn($unit)
            ->event('created')
            ->withProperties([
                'attributes' => $unit->only(['name', 'position', 'is_active']),
            ])
            ->log('unit.created');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('units.flash.created')]);

        return to_route('tenants.units.index', $tenant);
    }

    /**
     * Update the specified unit.
     */
    public function update(SaveUnitRequest $request, Tenant $tenant, Unit $unit): RedirectResponse
    {
        $before = $unit->only(['name', 'position', 'is_active']);

        $unit->update([
            'name' => $request->validated('name'),
            'position' => $request->validated('position') ?? $unit->position,
            'is_active' => $request->boolean('is_active'),
        ]);

        activity()
            ->performedOn($unit)
            ->event('updated')
            ->withProperties([
                'old' => $before,
                'attributes' => $unit->only(['name', 'position', 'is_active']),
            ])
            ->log('unit.updated');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('units.flash.updated')]);

        return to_route('tenants.units.index', $tenant);
    }

    /**
     * Delete the specified unit.
     */
    public function destroy(Tenant $tenant, Unit $unit): RedirectResponse
    {
        Gate::authorize('delete', [$unit, $tenant]);

        // Le participant et chaque accompagnateur doivent choisir une unite : une organisation
        // sans aucune unite rendrait le formulaire d'inscription impossible a remplir.
        if (Unit::count() <= 1) {
            return back()->withErrors(['unit' => __('units.errors.last_one')]);
        }

        // Une unite deja choisie par un invite ne se supprime pas (CLAUDE.md, « Unites ») : elle se
        // desactive, et l'historique des inscriptions reste lisible.
        if ($unit->isInUse()) {
            return back()->withErrors(['unit' => __('units.errors.in_use')]);
        }

        $before = $unit->only(['name', 'position', 'is_active']);

        $unit->delete();

        activity()
            ->event('deleted')
            ->withProperties(['old' => $before])
            ->log('unit.deleted');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('units.flash.deleted')]);

        return to_route('tenants.units.index', $tenant);
    }

    /**
     * Place a new unit at the end of the list.
     */
    private function nextPosition(): int
    {
        return (int) Unit::max('position') + 1;
    }
}
