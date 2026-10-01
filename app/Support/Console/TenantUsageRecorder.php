<?php

namespace App\Support\Console;

use App\Models\Tenant;
use App\Models\TenantUsage;
use App\Support\PlanLimits;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;

/**
 * Releve la consommation d'une organisation dans sa base et la range dans la base centrale
 * (README section 3). Appele par une tache planifiee pour toutes les organisations, et pour une
 * seule quand la console ouvre sa fiche ou change son plan : jamais pour toute la liste a
 * l'affichage.
 */
class TenantUsageRecorder
{
    /**
     * Refresh the counters of the given organisation, or leave them as they are when its
     * database cannot be opened (the health screen reports it).
     */
    public static function refresh(Tenant $tenant): ?TenantUsage
    {
        try {
            $limits = PlanLimits::for($tenant);

            $counters = [
                'active_events' => $limits->activeEvents(),
                'registrations' => $limits->registrations(),
                'members' => $limits->members(),
                'last_activity_at' => $tenant->run(fn () => DB::table('activity_log')->max('created_at')),
            ];
        } catch (TenantDatabaseDoesNotExistException) {
            // L'echec survient dans `initialize()`, avant le `finally` de `Tenant::run()`.
            tenancy()->end();

            return null;
        }

        return TenantUsage::updateOrCreate(['tenant_id' => $tenant->id], [
            ...$counters,
            'last_activity_at' => $counters['last_activity_at'] === null ? null : Carbon::parse($counters['last_activity_at']),
            'refreshed_at' => now(),
        ]);
    }

    public static function refreshAll(): void
    {
        Tenant::query()->each(fn (Tenant $tenant) => self::refresh($tenant));
    }
}
