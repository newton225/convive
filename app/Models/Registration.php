<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Une inscription a un evenement : le participant, ses accompagnateurs, le montant du, la
 * reservation. Vit dans la base du locataire (voir CLAUDE.md, « Multi-locataire »).
 *
 * `party_size` est fige a la creation (le participant plus ses accompagnateurs a cet instant) :
 * la disponibilite (README 2.2) se calcule dessus sans recompter les accompagnateurs a chaque
 * lecture. `hold_sequence` est le jeton d'unicite reel contre la survente (voir la migration qui
 * l'ajoute et `App\Actions\Registrations\HoldRegistration`).
 *
 * @property int $id
 * @property int $event_id
 * @property RegistrationStatus $status
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property int $unit_id
 * @property int $amount_due
 * @property int $party_size
 * @property Carbon|null $held_until
 * @property int|null $hold_sequence
 * @property string|null $resume_token_hash
 * @property Carbon|null $card_sent_at
 * @property Carbon|null $proof_reminder_j7_sent_at
 * @property Carbon|null $proof_reminder_j2_sent_at
 * @property Carbon|null $proof_reminder_j1_sent_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property int|null $cancelled_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read Unit $unit
 * @property-read Collection<int, RegistrationCompanion> $companions
 */
#[Fillable([
    'event_id', 'status', 'name', 'phone', 'email', 'unit_id', 'amount_due', 'party_size',
    'held_until', 'hold_sequence', 'resume_token_hash', 'card_sent_at',
    'proof_reminder_j7_sent_at', 'proof_reminder_j2_sent_at', 'proof_reminder_j1_sent_at',
    'cancelled_at', 'cancellation_reason', 'cancelled_by_user_id',
])]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les
     * connexions SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Longueur du jeton de reprise, en octets avant encodage. Voir `Event::PublicTokenBytes` :
     * meme raison, meme valeur.
     */
    public const ResumeTokenBytes = 32;

    /**
     * Statuts purges a l'echeance ou a l'epuisement des places (README 2.4) : tout ce qui n'est
     * pas finalise.
     *
     * `Cancelled` en est volontairement absent : contrairement aux quatre statuts ci-dessous,
     * ce n'est pas un echec du parcours invite mais une decision deliberee de l'organisation,
     * deja terminale. La purger reviendrait a effacer une trace que l'organisation a
     * explicitement choisi de garder (voir `App\Actions\Registrations\CancelRegistration`).
     *
     * @var array<int, RegistrationStatus>
     */
    public const UnfinalizedStatuses = [
        RegistrationStatus::Draft,
        RegistrationStatus::Held,
        RegistrationStatus::Expired,
        RegistrationStatus::ProofRejected,
    ];

    /**
     * Generate a resume token, returning the plain value to hand to the guest.
     *
     * Seule l'empreinte SHA-256 est destinee a etre stockee (CLAUDE.md, « Securite ») : le
     * jeton en clair ne doit jamais toucher la base, uniquement l'URL remise a l'invite.
     */
    public static function generateResumeToken(): string
    {
        return Str::random(self::ResumeTokenBytes * 2);
    }

    /**
     * Hash a plain resume token the same way it is stored, for lookup.
     */
    public static function hashResumeToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Get the deterministic token that authorizes a signed link back to this registration.
     *
     * La carte d'invitation et les rappels (README 2.7) sont envoyes bien apres la creation de
     * l'inscription, souvent depuis une tache planifiee sans requete HTTP en cours : le serveur
     * ne connait alors plus le jeton de reprise en clair, dont seule l'empreinte est stockee
     * (CLAUDE.md, « Securite »). Ce jeton-ci n'a pas ce probleme : recalculable a l'identique a
     * tout moment a partir du seul identifiant et de `APP_KEY`, jamais stocke nulle part.
     */
    public function notificationToken(): string
    {
        return hash_hmac('sha256', (string) $this->id, (string) config('app.key'));
    }

    /**
     * Get the absolute URL of a signed link back to this registration, or null when the tenant
     * or the event have nothing to build one from.
     *
     * Meme construction manuelle que `Event::publicUrl()`, pour la meme raison : ce lien est
     * genere depuis une tache planifiee, sans hote de requete courant a partir duquel
     * `route()`/`url()` pourraient le deriver.
     */
    public function signedResumeUrl(): ?string
    {
        $event = $this->event;
        $tenant = Tenant::current();

        if ($event->public_token === null || $tenant?->subdomain === null) {
            return null;
        }

        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $domain = $tenant->subdomain.'.'.config('convive.public_domain');

        return "{$scheme}://{$domain}{$port}/e/{$event->public_token}/register/{$this->id}/link"
            .'?signature='.$this->notificationToken();
    }

    /**
     * Determine whether this registration's hold has expired.
     *
     * Un `Held` dont le decompte est ecoule sans qu'une tache planifiee ait encore pose le
     * statut `Expired` (voir 2.4) : cote lecture, il doit deja se comporter comme expire.
     */
    public function holdHasExpired(): bool
    {
        return $this->status === RegistrationStatus::Held
            && $this->held_until !== null
            && $this->held_until->isPast();
    }

    /**
     * Get the event this registration belongs to.
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
     * Get the companions listed on this registration.
     *
     * @return HasMany<RegistrationCompanion, $this>
     */
    public function companions(): HasMany
    {
        return $this->hasMany(RegistrationCompanion::class);
    }

    /**
     * Get every proof ever submitted for this registration : un rejet (README 2.1) autorise
     * une nouvelle soumission, sans supprimer la precedente.
     *
     * @return HasMany<PaymentProof, $this>
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    /**
     * Get the proof currently awaiting verification, or the last one submitted.
     *
     * @return HasOne<PaymentProof, $this>
     */
    public function latestProof(): HasOne
    {
        return $this->hasOne(PaymentProof::class)->latestOfMany();
    }

    /**
     * Get the table this registration is seated at, if it has been assigned one (README 2.6).
     *
     * @return HasOne<RegistrationTableAssignment, $this>
     */
    public function tableAssignment(): HasOne
    {
        return $this->hasOne(RegistrationTableAssignment::class);
    }

    /**
     * Get the ticket issued for this registration once it is confirmed (README 2.8).
     *
     * @return HasOne<Ticket, $this>
     */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    /**
     * Scope the query to registrations that currently occupy a seat : confirmed, or held with
     * a countdown not yet expired (README 2.2). Priorite aux confirmees, mais les deux comptent
     * pour la disponibilite.
     *
     * @param  Builder<Registration>  $query
     */
    public function scopeOccupyingSeats(Builder $query): void
    {
        $query->where(function ($query) {
            $query->where('status', RegistrationStatus::Confirmed)
                ->orWhere(function ($query) {
                    $query->where('status', RegistrationStatus::Held)
                        ->where('held_until', '>', now());
                });
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'amount_due' => 'integer',
            'party_size' => 'integer',
            'held_until' => 'datetime',
            'hold_sequence' => 'integer',
            'card_sent_at' => 'datetime',
            'proof_reminder_j7_sent_at' => 'datetime',
            'proof_reminder_j2_sent_at' => 'datetime',
            'proof_reminder_j1_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
