<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Un acces de support (README section 3 et ecran 25) : un Proprietaire ouvre a une personne nommee
 * de l'equipe Convive la lecture de son organisation, pour 24 heures au plus.
 *
 * Base centrale : la console liste les acces ouverts a un compte, et l'acces se verifie avant
 * meme que la base de l'organisation ne soit ouverte. « En cours » n'est pas un statut stocke :
 * c'est un acces ni revoque ni arrive a echeance, lu a chaque requete. Aucune tache n'a donc a
 * tourner pour qu'un acces expire.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $operator_id
 * @property int|null $granted_by_id
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User $operator
 * @property-read User|null $grantedBy
 */
#[Fillable(['tenant_id', 'operator_id', 'granted_by_id', 'expires_at', 'revoked_at', 'revoked_by_id'])]
class SupportAccessGrant extends Model
{
    use CentralConnection;

    /**
     * Durees proposees, en heures. Le plafond de 24 heures est une decision du proprietaire du
     * projet (2026-09-28), il ne se saisit pas en texte libre.
     *
     * @var array<int, int>
     */
    public const DurationsInHours = [1, 4, 12, 24];

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Scope the query to the accesses still open : neither revoked nor past their term.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * Determine whether this access still lets its holder read the organisation.
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * Get the moment this access stopped, or will stop, granting anything.
     */
    public function endedAt(): Carbon
    {
        return $this->revoked_at ?? $this->expires_at;
    }

    /**
     * Get the organisation that opened this access.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the member of the Convive team this access was opened to.
     *
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * Get the owner who opened this access.
     *
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    /**
     * Get the pages consulted through this access.
     *
     * @return HasMany<SupportAccessView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(SupportAccessView::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
