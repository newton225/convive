<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Une demande d'aide (README section 3) : un Proprietaire previent l'equipe Convive qu'il veut lui
 * ouvrir son espace, quand personne n'y est visible pour recevoir l'acces. Elle n'ouvre rien : la
 * personne qui la prend en charge se rend visible, et c'est le Proprietaire qui ouvre ensuite
 * l'acces.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $requested_by_id
 * @property string $reason
 * @property int|null $taken_by_id
 * @property Carbon|null $taken_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User|null $requestedBy
 * @property-read User|null $takenBy
 */
#[Fillable(['tenant_id', 'requested_by_id', 'reason', 'taken_by_id', 'taken_at', 'closed_at'])]
class SupportAccessRequest extends Model
{
    use CentralConnection;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Scope the query to the requests still waiting : ni annulees, ni suivies d'un acces.
     *
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    public function isPending(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Get the organisation asking for help.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the owner who sent the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * Get the member of the Convive team who took the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
