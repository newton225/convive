<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Une place en liste d'attente (README 2.3) : les donnees du participant et de ses
 * accompagnateurs, en attente qu'une place se libere. `companions` reste un JSON tant que
 * l'entree n'est pas convertie en inscription (voir `App\Actions\Waitlist\FinalizeWaitlistEntry`) :
 * ce n'est qu'un brouillon de salle d'attente, pas encore un enregistrement de premier ordre.
 *
 * Vit dans la base du locataire (voir CLAUDE.md, « Multi-locataire »).
 *
 * @property int $id
 * @property int $event_id
 * @property WaitlistStatus $status
 * @property string $name
 * @property string $phone
 * @property int $unit_id
 * @property int $party_size
 * @property array<int, array{name: string, unit_id: int}> $companions
 * @property string $resume_token_hash
 * @property Carbon|null $invited_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read Unit $unit
 */
#[Fillable(['event_id', 'status', 'name', 'phone', 'unit_id', 'party_size', 'companions', 'resume_token_hash', 'invited_at', 'expires_at'])]
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les
     * connexions SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Duree de validite du lien de finalisation, une fois la place proposee (README 2.3).
     */
    public const InviteDurationHours = 6;

    /**
     * Longueur du jeton de reprise, en octets avant encodage. Voir `Registration::ResumeTokenBytes`.
     */
    public const ResumeTokenBytes = 32;

    public static function generateResumeToken(): string
    {
        return Str::random(self::ResumeTokenBytes * 2);
    }

    public static function hashResumeToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Get this entry's rank among those still waiting, first arrived first served.
     *
     * Calcule a la lecture plutot que stocke : quand une entree quitte la file (promue,
     * annulee), personne n'a besoin de renumeroter les suivantes.
     */
    public function position(): int
    {
        if ($this->status !== WaitlistStatus::Waiting) {
            return 0;
        }

        return 1 + static::query()
            ->where('event_id', $this->event_id)
            ->where('status', WaitlistStatus::Waiting)
            ->where('id', '<', $this->id)
            ->count();
    }

    /**
     * Determine whether the six-hour invite window has closed.
     */
    public function inviteHasExpired(): bool
    {
        return $this->status === WaitlistStatus::Invited
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Get the event this entry is waiting for.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the unit of the participant themself, as opposed to their companions.
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
            'status' => WaitlistStatus::class,
            'party_size' => 'integer',
            'companions' => 'array',
            'invited_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
