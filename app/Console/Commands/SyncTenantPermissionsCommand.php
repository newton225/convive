<?php

namespace App\Console\Commands;

use App\Actions\Tenants\SyncPermissionCatalogue;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;

/**
 * Applique le catalogue de permissions a toutes les organisations existantes, a jouer apres
 * `tenants:migrate` a chaque deploiement (et planifiee chaque jour en filet de securite).
 *
 * Boucle sur les locataires et initialise la tenancy a chaque tour (CLAUDE.md, « Tenancy non
 * initialisee ») : jamais une requete large sur une base qui n'est pas la bonne.
 */
#[Signature('tenants:sync-permissions')]
#[Description('Cree les permissions ajoutees au catalogue dans chaque organisation et les donne a son Proprietaire')]
class SyncTenantPermissionsCommand extends Command
{
    public function handle(SyncPermissionCatalogue $sync): int
    {
        $failed = false;

        Tenant::query()->each(function (Tenant $tenant) use ($sync, &$failed) {
            try {
                $tenant->run(fn () => $sync->handle());
                $this->components->task("Organisation {$tenant->id}");
            } catch (TenantDatabaseDoesNotExistException) {
                // L'echec survient dans `initialize()`, avant le `finally` de `Tenant::run()` : sans
                // `end()`, la tenancy reste a moitie posee et fausse les organisations suivantes.
                tenancy()->end();

                // Un enregistrement sans base est une organisation cassee, a signaler, pas une
                // raison d'arreter la mise a jour des autres.
                $this->components->warn("Organisation {$tenant->id} : base absente, ignoree.");
                $failed = true;
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
