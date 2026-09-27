<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Les messages envoyes aux invites de l'organisation pendant un mois (SECURITY.md H5). Vit dans la
 * base du locataire (voir CLAUDE.md, « Multi-locataire »).
 *
 * @property int $id
 * @property string $month
 * @property int $count
 * @property Carbon|null $quota_alerted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['month', 'count', 'quota_alerted_at'])]
class MessageUsage extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the usage row of the current month, created on first use.
     */
    public static function currentMonth(): self
    {
        return self::firstOrCreate(['month' => now()->format('Y-m')], ['count' => 0]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'quota_alerted_at' => 'datetime',
        ];
    }
}
