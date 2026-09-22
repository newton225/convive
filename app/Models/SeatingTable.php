<?php

namespace App\Models;

use Database\Factories\SeatingTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Une table physique d'un evenement (README 2.6, ecran 21). Vit dans la base du locataire (voir
 * CLAUDE.md, « Multi-locataire ») : aucune colonne `tenant_id`.
 *
 * @property int $id
 * @property int $event_id
 * @property int $number
 * @property int $capacity
 * @property int|null $reserved_unit_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read Unit|null $reservedUnit
 * @property-read Collection<int, RegistrationTableAssignment> $assignments
 */
#[Fillable(['event_id', 'number', 'capacity', 'reserved_unit_id'])]
class SeatingTable extends Model
{
    /** @use HasFactory<SeatingTableFactory> */
    use HasFactory;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les
     * connexions SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the event this table belongs to.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the unit this table is reserved for, if any.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function reservedUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'reserved_unit_id');
    }

    /**
     * Get the registrations seated at this table.
     *
     * @return HasMany<RegistrationTableAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(RegistrationTableAssignment::class);
    }

    /**
     * Get the number of seats still free at this table.
     *
     * Suppose `assignments.registration` deja charge (voir
     * `App\Actions\Seating\AssignTable::candidateTables()`) : evite une requete par table lors
     * du choix d'une table.
     */
    public function remainingCapacity(): int
    {
        return $this->capacity - $this->assignments->sum(
            fn (RegistrationTableAssignment $assignment) => $assignment->registration->party_size,
        );
    }

    /**
     * Get the units already seated at this table.
     *
     * @return SupportCollection<int, int>
     */
    public function occupantUnitIds(): SupportCollection
    {
        return $this->assignments->pluck('registration.unit_id')->unique()->values();
    }

    /**
     * Scope the query to the tables of a given event, in table-number order.
     *
     * @param  Builder<SeatingTable>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('number');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'capacity' => 'integer',
        ];
    }
}
