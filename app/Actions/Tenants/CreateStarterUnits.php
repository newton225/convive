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
     * Comme les profils, c'est un point de depart : l'exploitant renomme, ajoute et desactive
     * a sa guise. `Aucune` arrive en dernier, parce qu'elle se choisit par defaut.
     */
    public function handle(): void
    {
        foreach (Unit::Starters as $position => $name) {
            $unit = new Unit(['name' => $name, 'position' => $position]);
            $unit->save();
        }
    }
}
