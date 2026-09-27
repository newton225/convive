<?php

namespace App\Models;

use Database\Factories\ShowcaseEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Une ligne par evenement annonce sur la vitrine du site produit (CLAUDE.md, « Annonce sur le
 * site produit »). Base centrale : la vitrine ne lit jamais les bases des locataires a chaque
 * affichage, cette table est la copie de lecture tenue a jour par
 * `App\Actions\Events\SaveEvent`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $event_id
 * @property string $name
 * @property string $organisation_name
 * @property Carbon|null $starts_at
 * @property string $public_url
 * @property Carbon $announced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['tenant_id', 'event_id', 'name', 'organisation_name', 'starts_at', 'public_url', 'announced_at'])]
class ShowcaseEvent extends Model
{
    /** @use HasFactory<ShowcaseEventFactory> */
    use CentralConnection, HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the tenant this showcased event belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope the query to the display order of the showcase : most recently announced first.
     *
     * @param  Builder<ShowcaseEvent>  $query
     */
    public function scopeRecentlyAnnounced(Builder $query): void
    {
        $query->orderByDesc('announced_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'announced_at' => 'datetime',
        ];
    }
}
