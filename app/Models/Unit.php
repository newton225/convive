<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Une unite d'une organisation. Vit dans la base du locataire (voir CLAUDE.md,
 * « Multi-locataire ») : aucune colonne `tenant_id`, la base elle-meme est la frontiere.
 *
 * Liste fermee cote invite : on choisit dans la liste, on n'en saisit pas une nouvelle.
 * `Aucune` est un choix valide, un champ vide ne l'est pas.
 *
 * @property int $id
 * @property string $name
 * @property int $position
 * @property bool $is_active
 * @property bool $is_none
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'position', 'is_active'])]
class Unit extends Model
{
    /**
     * Tables qui designent une unite. Tant que l'une d'elles la porte, l'unite ne se supprime pas :
     * elle se desactive (`is_active`), pour ne pas casser l'historique des inscriptions.
     *
     * @var array<string, string>
     */
    private const References = [
        'registrations' => 'unit_id',
        'registration_companions' => 'unit_id',
        'waitlist_entries' => 'unit_id',
        'seating_tables' => 'reserved_unit_id',
    ];

    /**
     * Determine whether a registration, a companion, a waitlist entry or a table still carries
     * the unit.
     */
    public function isInUse(): bool
    {
        foreach (self::References as $table => $column) {
            if ($this->getConnection()->table($table)->where($column, $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les
     * connexions SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Le nom de l'unite qui vaut « pas d'unite ». C'est un choix, pas une absence de choix.
     */
    public const None = 'Aucune';

    /**
     * Les unites creees a l'ouverture d'un espace. Point de depart remaniable, comme les
     * profils : ce sont celles de l'organisation qui a inspire le produit.
     *
     * @var array<int, string>
     */
    public const Starters = [
        'ELIAKIM',
        'QODESH',
        'SENTINELLES',
        'ELISHAMA',
        'CHOSEN',
        'ETAT MAJOR',
        self::None,
    ];

    /**
     * Scope the query to the units offered to guests.
     *
     * @param  Builder<Unit>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope the query to the display order chosen by the operator, « Aucune » always last
     * whatever the positions given to the other units.
     *
     * @param  Builder<Unit>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('is_none')->orderBy('position')->orderBy('name');
    }

    /**
     * Determine whether this unit is the "no unit" choice.
     *
     * Reconnue a sa colonne, posee a sa creation et jamais par un formulaire : c'est elle qui la
     * protege (ni renommee, ni desactivee, ni supprimee, voir `UnitPolicy`).
     */
    public function isNone(): bool
    {
        return $this->is_none;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_none' => 'boolean',
            'position' => 'integer',
        ];
    }
}
