<?php

namespace App\Actions\Console;

use App\Actions\Tenants\SyncPermissionCatalogue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Console\ConsoleJournal;
use App\Support\Console\TenantDatabaseHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Remettre d'aplomb la base d'une organisation depuis la console (README ecran 31) : la creer si
 * elle manque, y rejouer les migrations en retard, puis completer son catalogue de permissions.
 * Les memes gestes qu'au deploiement (`tenants:migrate`, `tenants:sync-permissions`), pour une
 * seule organisation.
 *
 * Les migrations n'ajoutent que des tables et des colonnes (CLAUDE.md, « Base de donnees ») :
 * rejouer ne detruit rien.
 */
class RepairTenantDatabase
{
    /**
     * @throws RuntimeException when the migrations did not complete
     */
    public function handle(Tenant $tenant, User $actor): void
    {
        // Un double clic ne lance pas deux migrations a la fois sur la meme base.
        Cache::lock("console:repair-database:{$tenant->id}", 120)->block(5, function () use ($tenant, $actor) {
            $database = $tenant->database();

            if (! $database->manager()->databaseExists($database->getName())) {
                $database->manager()->createDatabase($tenant);
            }

            $exitCode = Artisan::call('tenants:migrate', ['--tenants' => [$tenant->getTenantKey()]]);

            ConsoleJournal::record('database_repaired', $actor, $tenant, ['exit_code' => $exitCode]);

            if ($exitCode !== 0) {
                throw new RuntimeException(Artisan::output());
            }

            $tenant->run(fn () => app(SyncPermissionCatalogue::class)->handle());
        });

        TenantDatabaseHealth::forget();
    }
}
