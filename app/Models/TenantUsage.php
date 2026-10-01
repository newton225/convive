<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Les compteurs de consommation d'une organisation, releves dans sa base et gardes au centre
 * (README section 3) : la console les lit ici, jamais dans les bases des organisations a chaque
 * affichage. Tenus par `App\Support\Console\TenantUsageRecorder`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $active_events
 * @property int $registrations
 * @property int $members
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $refreshed_at
 * @property-read Tenant $tenant
 */
#[Fillable(['tenant_id', 'active_events', 'registrations', 'members', 'last_activity_at', 'refreshed_at'])]
class TenantUsage extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_events' => 'integer',
            'registrations' => 'integer',
            'members' => 'integer',
            'last_activity_at' => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }
}
