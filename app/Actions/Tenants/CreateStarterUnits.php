<?php

namespace App\Actions\Tenants;

use App\Models\Unit;

/**
 * Opere sur les unites de l'organisation dont la base est active au moment de l'appel : voir
 * CLAUDE.md, « Multi-locataire ». Le seul appelant, `CreateTenant`, l'invoque a l'interieur de
 * `$tenant->run()`.
 */
class CreateStarterUnits
{
    /**
     * Create the units a tenant starts with.
     *
     * Un point de depart : l'exploitant renomme, ajoute et desactive a sa guise, sauf `Aucune`,
     * protegee et toujours en dernier (voir `Unit::isNone()`).
     */
    public function handle(): void
    {
        foreach (Unit::Starters as $position => $name) {
            $unit = new Unit(['name' => $name, 'position' => $position]);
            $unit->is_none = $name === Unit::None;
            $unit->save();
        }
    }
}
