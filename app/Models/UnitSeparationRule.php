<?php

namespace App\Models;

use Database\Factories\UnitSeparationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une regle de separation entre deux unites, pour un evenement (README 2.6) : une option par
 * evenement, pas un reglage de locataire. Vit dans la base du locataire (voir CLAUDE.md,
 * « Multi-locataire »).
 *
 * La paire n'est pas ordonnee : `unit_a_id` et `unit_b_id` sont canonises a l'ecriture
 * (`canonicalPair()`) pour qu'une meme regle ne puisse pas exister deux fois, une fois dans
 * chaque sens.
 *
 * @property int $id
 * @property int $event_id
 * @property int $unit_a_id
 * @property int $unit_b_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read Unit $unitA
 * @property-read Unit $unitB
 */
#[Fillable(['event_id', 'unit_a_id', 'unit_b_id'])]
class UnitSeparationRule extends Model
{
    /** @use HasFactory<UnitSeparationRuleFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Order a pair of unit ids canonically, smallest first : the columns to write, whichever
     * order the two units were picked in.
     *
     * @return array{0: int, 1: int}
     */
    public static function canonicalPair(int $unitId, int $otherUnitId): array
    {
        return $unitId < $otherUnitId ? [$unitId, $otherUnitId] : [$otherUnitId, $unitId];
    }

    /**
     * Determine whether this rule concerns the given pair of units, in either order.
     */
    public function concerns(int $unitId, int $otherUnitId): bool
    {
        [$a, $b] = self::canonicalPair($unitId, $otherUnitId);

        return $this->unit_a_id === $a && $this->unit_b_id === $b;
    }

    /**
     * Get the event this rule applies to.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unitA(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_a_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unitB(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_b_id');
    }
}
