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
 * @property int $holder_position
 * @property string|null $holder_name
 * @property int|null $holder_unit_id
 * @property Carbon $issued_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Registration $registration
 * @property-read Unit|null $holderUnit
 * @property-read TicketArrival|null $arrival
 * @property-read Collection<int, ScanEvent> $scanEvents
 */
#[Fillable(['registration_id', 'nonce', 'key_version', 'issued_at', 'reminder_sent_at', 'holder_position', 'holder_name', 'holder_unit_id'])]
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
     * Position de l'invite principal : ses accompagnateurs suivent, 1, 2... (README 2.8, un billet
     * par personne).
     */
    public const GuestPosition = 0;

    /**
     * Get the unit of the companion holding this ticket (null for the main guest).
     *
     * @return BelongsTo<Unit, $this>
     */
    public function holderUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'holder_unit_id');
    }

    public function isCompanion(): bool
    {
        return $this->holder_position !== self::GuestPosition;
    }

    /**
     * Get the name of the person this ticket admits.
     */
    public function holderName(): string
    {
        return $this->holder_name ?? $this->registration->name;
    }

    /**
     * Get the unit of the person this ticket admits.
     */
    public function holderUnitName(): string
    {
        return $this->holderUnit->name ?? $this->registration->unit->name;
    }

    /**
     * Signature of this ticket's individual link : HMAC recalculable, jamais stocke, comme
     * `Registration::notificationToken()`. Le lien montre un QR, donc permet d'entrer : il ne doit
     * pas pouvoir se deviner.
     */
    public function shareSignature(): string
    {
        return hash_hmac('sha256', 'ticket:'.$this->id, (string) config('app.key'));
    }

    /**
     * Get the absolute URL of this ticket alone, the one the guest forwards to a companion, or
     * null when the tenant or the event have nothing to build one from.
     *
     * Construction manuelle, comme `Registration::signedResumeUrl()` : l'hote vient du
     * sous-domaine de l'organisation, pas de la requete courante.
     */
    public function shareUrl(): ?string
    {
        return $this->signedPublicUrl('');
    }

    /**
     * Get the absolute URL of this ticket alone as a PDF, to keep offline and show at the door
     * when the connection is unreliable (README 2.8). Meme signature que le lien individuel :
     * qui peut voir le billet peut le telecharger.
     */
    public function pdfUrl(): ?string
    {
        return $this->signedPublicUrl('/pdf');
    }

    private function signedPublicUrl(string $suffix): ?string
    {
        $event = $this->registration->event;
        $tenant = Tenant::current();

        if ($event->public_token === null || $tenant?->subdomain === null) {
            return null;
        }

        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $domain = $tenant->subdomain.'.'.config('convive.public_domain');

        return "{$scheme}://{$domain}{$port}/e/{$event->public_token}/ticket/{$this->id}{$suffix}"
            .'?signature='.$this->shareSignature();
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
        $event = $this->registration->event;
        $keyPair = $event->ensureSigningKeyPair();

        return TicketToken::sign([
            'tenant_id' => Tenant::current()?->id,
            'event_id' => $this->registration->event_id,
            'registration_id' => $this->registration_id,
            'nonce' => $this->nonce,
            // Qui ce billet fait entrer dans le groupe (README 2.8) : l'appareil hors ligne ne
            // marque ainsi « deja vu » que cette personne, pas tout le groupe.
            'holder' => $this->holder_position,
            'key_version' => $keyPair['version'],
            'not_after' => $event->ticketValidUntil()?->getTimestamp(),
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
            'holder_position' => 'integer',
            'issued_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }
}
