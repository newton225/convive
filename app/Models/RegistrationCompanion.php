<?php

namespace App\Models;

use Database\Factories\RegistrationCompanionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un accompagnateur d'une inscription : nom et unite, comme le participant lui-meme
 * (README 2.5). Vit dans la base du locataire (voir CLAUDE.md, « Multi-locataire »).
 *
 * @property int $id
 * @property int $registration_id
 * @property string $name
 * @property int $unit_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Registration $registration
 * @property-read Unit $unit
 */
#[Fillable(['name', 'unit_id', 'position'])]
class RegistrationCompanion extends Model
{
    /** @use HasFactory<RegistrationCompanionFactory> */
    use HasFactory;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les
     * connexions SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the registration this companion belongs to.
     *
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * Get the unit this companion belongs to.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
