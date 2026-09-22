<?php

namespace App\Models;

use Database\Factories\StatementImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un releve importe pour un evenement (README 2.10, ecran 19). Vit dans la base du locataire
 * (voir CLAUDE.md, « Multi-locataire »).
 *
 * `imported_by_user_id` reference `User`, qui vit dans la base centrale : pas de cle etrangere
 * possible entre deux bases physiques distinctes.
 *
 * @property int $id
 * @property int $event_id
 * @property int $imported_by_user_id
 * @property string $original_filename
 * @property string $content_hash
 * @property int $row_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read Collection<int, StatementLine> $lines
 */
#[Fillable(['event_id', 'imported_by_user_id', 'original_filename', 'content_hash', 'row_count'])]
class StatementImport extends Model
{
    /** @use HasFactory<StatementImportFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the event this statement was imported for.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the lines of this statement.
     *
     * @return HasMany<StatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StatementLine::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
        ];
    }
}
