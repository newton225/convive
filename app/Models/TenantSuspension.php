<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Une suspension manuelle d'organisation par l'editeur (README section 3), avec son motif
 * obligatoire. En cours tant que `lifted_at` est nul. La suspension automatique pour impaye n'est
 * pas ici : elle est l'etat de l'abonnement, et se leve par le paiement.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $reason
 * @property int|null $suspended_by_id
 * @property Carbon|null $lifted_at
 * @property int|null $lifted_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['tenant_id', 'reason', 'suspended_by_id', 'lifted_at', 'lifted_by_id'])]
class TenantSuspension extends Model
{
    use CentralConnection;

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
            'lifted_at' => 'datetime',
        ];
    }
}
