<?php

namespace App\Models;

use Database\Factories\RegistrationTableAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * L'attribution d'une inscription a une table (README 2.6). Vit dans la base du locataire (voir
 * CLAUDE.md, « Multi-locataire »).
 *
 * @property int $id
 * @property int $registration_id
 * @property int $seating_table_id
 * @property bool $assigned_manually
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Registration $registration
 * @property-read SeatingTable $seatingTable
 */
#[Fillable(['registration_id', 'seating_table_id', 'assigned_manually'])]
class RegistrationTableAssignment extends Model
{
    /** @use HasFactory<RegistrationTableAssignmentFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the registration seated by this assignment.
     *
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * Get the table this assignment seats the registration at.
     *
     * @return BelongsTo<SeatingTable, $this>
     */
    public function seatingTable(): BelongsTo
    {
        return $this->belongsTo(SeatingTable::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_manually' => 'boolean',
        ];
    }
}
