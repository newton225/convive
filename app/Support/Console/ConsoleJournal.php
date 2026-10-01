<?php

namespace App\Support\Console;

use App\Models\ConsoleActionLog;
use App\Models\Tenant;
use App\Models\User;

/**
 * Ecrit le journal central de la console (README section 3) : toute action de l'editeur y laisse
 * l'acteur, l'organisation concernee, ce qui a change, et l'adresse IP. Les types sont ceux que
 * l'ecran sait nommer (`console.audit.types`).
 */
class ConsoleJournal
{
    /**
     * Record one action of the console.
     *
     * @param  array<string, mixed>  $properties  what changed, before and after when it applies
     */
    public static function record(string $type, ?User $actor, ?Tenant $tenant = null, array $properties = []): ConsoleActionLog
    {
        return ConsoleActionLog::create([
            'type' => $type,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'tenant_id' => $tenant?->id,
            'organisation' => $tenant?->name,
            'properties' => $properties === [] ? null : $properties,
            // Null hors requete : une tache planifiee agit sans adresse.
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }
}
