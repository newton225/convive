<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Une page de l'organisation consultee pendant un acces de support (README section 3). Ecriture
 * seule : la ligne n'est jamais modifiee ni supprimee par l'application. C'est la trace centrale ;
 * la meme consultation figure aussi au journal de l'organisation.
 *
 * @property int $id
 * @property int $support_access_grant_id
 * @property string $page
 * @property string|null $route
 * @property Carbon $viewed_at
 * @property-read SupportAccessGrant $grant
 */
#[Fillable(['support_access_grant_id', 'page', 'route', 'viewed_at'])]
class SupportAccessView extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the access this page was consulted through.
     *
     * @return BelongsTo<SupportAccessGrant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(SupportAccessGrant::class, 'support_access_grant_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }
}
