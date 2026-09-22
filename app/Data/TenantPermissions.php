<?php

namespace App\Data;

use App\Enums\TenantPermission;

/**
 * Les permissions effectives d'un membre sur le locataire courant, telles qu'envoyees au
 * front. Elles servent a masquer ce qui est interdit ; elles ne remplacent jamais la
 * verification serveur, qui est refaite a chaque route et a chaque action.
 *
 * N'implemente delibarement pas `Arrayable` : `Inertia\PropsResolver` aplatit tout objet
 * `Arrayable` en tableau brut via `toArray()` avant l'envoi au front (`{$values}` au lieu de
 * `{"values": {$values}}`). Le type TypeScript `TenantPermissions` et `can()`
 * (`resources/js/lib/permissions.ts`) attendent tous deux `{ values: string[] }` : ce bug,
 * present partout ou ce prop `permissions` etait passe, ne s'est jamais vu dans les tests (aucun
 * n'execute React) et faisait planter chaque page authentifiee des que `can()` y touchait.
 */
readonly class TenantPermissions
{
    /**
     * @param  array<int, string>  $values
     */
    public function __construct(public array $values)
    {
        //
    }

    public function has(TenantPermission $permission): bool
    {
        return in_array($permission->value, $this->values, true);
    }
}
