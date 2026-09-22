<?php

namespace App\Models;

use App\Support\TicketToken;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Le billet d'une inscription confirmee (README 2.8, ecran 7). Vit dans la base du locataire
 * (voir CLAUDE.md, « Multi-locataire »).
 *
 * @property int $id
 * @property int $registration_id
 * @property string $nonce
 * @property int $key_version
 * @property Carbon $issued_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Registration $registration
 * @property-read TicketArrival|null $arrival
 * @property-read Collection<int, ScanEvent> $scanEvents
 */
#[Fillable(['registration_id', 'nonce', 'key_version', 'issued_at', 'reminder_sent_at'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Longueur du nonce, en octets avant encodage. Voir `Event::PublicTokenBytes` : meme raison,
     * aucun identifiant devinable dans la charge signee du QR.
     */
    public const NonceBytes = 32;

    /**
     * Generate the random part of a ticket's signed payload.
     */
    public static function generateNonce(): string
    {
        return Str::random(self::NonceBytes * 2);
    }

    /**
     * Get the registration this ticket was issued for.
     *
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * Get the first accepted scan of this ticket, if it has been used yet.
     *
     * @return HasOne<TicketArrival, $this>
     */
    public function arrival(): HasOne
    {
        return $this->hasOne(TicketArrival::class);
    }

    /**
     * Get every scan attempt made against this ticket.
     *
     * @return HasMany<ScanEvent, $this>
     */
    public function scanEvents(): HasMany
    {
        return $this->hasMany(ScanEvent::class);
    }

    /**
     * Get the signed content of this ticket's QR code.
     *
     * Recalcule a chaque appel plutot que stocke : la charge n'a rien de secret, seule la cle
     * privee de l'evenement l'est, et un jeton recalcule reste identique tant que le nonce et
     * la version de cle ne changent pas.
     */
    public function signedToken(): string
    {
        $keyPair = $this->registration->event->ensureSigningKeyPair();

        return TicketToken::sign([
            'tenant_id' => Tenant::current()?->id,
            'event_id' => $this->registration->event_id,
            'registration_id' => $this->registration_id,
            'nonce' => $this->nonce,
            'key_version' => $this->key_version,
        ], $keyPair['secret']);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key_version' => 'integer',
            'issued_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }
}
