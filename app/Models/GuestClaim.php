<?php

namespace App\Models;

use App\Enums\ClaimCategory;
use App\Enums\ClaimStatus;
use Database\Factories\GuestClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une reclamation d'un invite sur son dossier (decision du 2026-10-09). Vit dans la base du
 * locataire : aucune colonne `tenant_id`.
 *
 * @property int $id
 * @property int $registration_id
 * @property ClaimCategory $category
 * @property string $message
 * @property ClaimStatus $status
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Registration $registration
 */
#[Fillable(['registration_id', 'category', 'message', 'status', 'resolved_at', 'resolved_by_user_id'])]
class GuestClaim extends Model
{
    /** @use HasFactory<GuestClaimFactory> */
    use HasFactory;

    /**
     * Longueur maximale du message : assez pour expliquer, pas pour deposer un document.
     */
    public const MessageMaxLength = 1000;

    /**
     * Reclamations ouvertes qu'un meme dossier peut avoir en meme temps : au-dela, l'invite attend
     * qu'on lui reponde plutot que d'inonder l'equipe.
     */
    public const MaxOpenPerRegistration = 3;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les connexions
     * SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @param  Builder<GuestClaim>  $query
     * @return Builder<GuestClaim>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ClaimStatus::Open);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ClaimCategory::class,
            'status' => ClaimStatus::class,
            'resolved_at' => 'datetime',
        ];
    }
}
