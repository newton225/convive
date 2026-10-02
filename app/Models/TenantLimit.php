<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Les limites propres a une organisation, reglees par l'editeur depuis la console (README section
 * 3). Une valeur remplace la limite du plan pour cette organisation seulement ; une valeur nulle
 * suit le plan. Voir `Tenant::limit()`, seul endroit qui tranche entre les deux.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $max_active_events
 * @property int|null $max_registrations
 * @property int|null $max_members
 * @property int|null $max_messages_per_month
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['tenant_id', 'max_active_events', 'max_registrations', 'max_members', 'max_messages_per_month'])]
class TenantLimit extends Model
{
    use CentralConnection;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Les limites qu'une organisation peut recevoir en propre : les memes colonnes que sur `Plan`.
     *
     * @var array<int, string>
     */
    public const Quotas = ['max_active_events', 'max_registrations', 'max_members', 'max_messages_per_month'];

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
            'max_active_events' => 'integer',
            'max_registrations' => 'integer',
            'max_members' => 'integer',
            'max_messages_per_month' => 'integer',
        ];
    }
}
