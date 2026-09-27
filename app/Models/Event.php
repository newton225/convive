<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Un evenement d'une organisation.
 *
 * @property int $id
 * @property string $name
 * @property string|null $subtitle
 * @property EventStatus $status
 * @property CarbonImmutable|null $starts_at
 * @property string|null $venue
 * @property string|null $venue_address
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property int $table_count
 * @property int $seats_per_table
 * @property int $price_per_person
 * @property int $companion_limit
 * @property CarbonImmutable|null $registration_deadline
 * @property CarbonImmutable|null $purge_at
 * @property CarbonImmutable|null $seats_low_alerted_at
 * @property CarbonImmutable|null $purge_notice_sent_at
 * @property CarbonImmutable|null $invitations_send_at
 * @property int $hold_duration_minutes
 * @property string|null $public_token
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $announced_at
 * @property string|null $qr_public_key
 * @property string|null $qr_secret_key
 * @property int $qr_key_version
 * @property bool $reminder_j7_enabled
 * @property bool $reminder_j2_enabled
 * @property bool $reminder_j1_enabled
 * @property bool $reminder_day_of_enabled
 * @property bool $rule_scheduled_send
 * @property bool $rule_auto_seating
 * @property bool $rule_allow_without_proof
 * @property bool $rule_proof_legibility
 * @property bool $rule_purge_on_exhaustion
 * @property bool $rule_temporary_hold
 * @property bool $rule_phone_verification
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, PaymentAccount> $paymentAccounts
 */
#[Fillable([
    'name', 'subtitle', 'starts_at', 'venue', 'venue_address',
    'primary_color', 'secondary_color',
    'table_count', 'seats_per_table', 'price_per_person', 'companion_limit',
    'registration_deadline', 'purge_at', 'invitations_send_at', 'hold_duration_minutes',
    'reminder_j7_enabled', 'reminder_j2_enabled', 'reminder_j1_enabled', 'reminder_day_of_enabled',
    'rule_scheduled_send', 'rule_auto_seating', 'rule_allow_without_proof',
    'rule_proof_legibility', 'rule_purge_on_exhaustion', 'rule_temporary_hold', 'rule_phone_verification',
])]
#[Hidden(['qr_secret_key'])]
class Event extends Model implements HasMedia
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * Nom de la collection du visuel de l'evenement (README ecran 13). Un seul fichier, comme
     * les fichiers de marque de l'organisation : pas de catalogue ici, un evenement n'a qu'un
     * visuel.
     */
    public const VisualCollection = 'visual';

    /**
     * Fixe le format plutot que de le demander a la connexion active (`getDateFormat()` sans
     * cette valeur appelle `getConnection()`) : ce modele vit dans la base du locataire, dont
     * la connexion nommee `tenant` est purgee des que la tenancy se termine (voir CLAUDE.md,
     * « Multi-locataire »). Un simple acces a un champ date sur une instance conservee au dela
     * de cette frontiere echouerait sinon, alors que SQLite (central comme locataire) utilise
     * ce format partout : la valeur ne varie jamais d'une connexion a l'autre.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Longueur du jeton du lien public, en octets avant encodage.
     *
     * Trente-deux octets : aucun identifiant sequentiel devinable dans une URL publique, et
     * de quoi rendre l'enumeration des evenements d'une organisation sans espoir.
     */
    public const PublicTokenBytes = 32;

    /**
     * Plafond d'accompagnateurs autorise par le produit (README 2.5).
     */
    public const MaximumCompanionLimit = 10;

    /**
     * Duree de reservation par defaut, en minutes (README 2.1).
     */
    public const DefaultHoldDurationMinutes = 10;

    /**
     * Le visuel de l'evenement (README ecran 13), un seul exemplaire. Le SVG est refuse comme
     * pour les fichiers de marque de l'organisation : il peut porter du script, et rien ne le
     * justifie pour une image (CLAUDE.md, « Fichiers de marque »).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::VisualCollection)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Get a signed, expiring URL for the event's visual, or null when it is not set.
     */
    public function visualUrl(int $minutes = 30): ?string
    {
        $media = $this->getFirstMedia(self::VisualCollection);

        return $media instanceof Media
            ? $media->getTemporaryUrl(now()->addMinutes($minutes))
            : null;
    }

    /**
     * Get the colours to apply on this event's public page, falling back to the organisation's
     * brand colours when the event does not override them.
     *
     * @return array{primary: string, secondary: string}
     */
    public function colors(): array
    {
        $tenantColors = Tenant::current()?->brandingOrCreate()->colors() ?? [
            'primary' => TenantBranding::DefaultPrimaryColor,
            'secondary' => TenantBranding::DefaultSecondaryColor,
        ];

        return [
            'primary' => $this->primary_color ?? $tenantColors['primary'],
            'secondary' => $this->secondary_color ?? $tenantColors['secondary'],
        ];
    }

    /**
     * Get the payment accounts offered for this event.
     *
     * @return BelongsToMany<PaymentAccount, $this>
     */
    public function paymentAccounts(): BelongsToMany
    {
        return $this->belongsToMany(PaymentAccount::class);
    }

    /**
     * Get the registrations made for this event.
     *
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Get the total number of seats, derived from the room plan.
     *
     * La capacite ne se saisit pas : c'est le plan de salle qui fait foi le jour J.
     */
    public function capacity(): int
    {
        return $this->table_count * $this->seats_per_table;
    }

    /**
     * Get the amount owed for a registration with the given number of companions.
     *
     * Montant du = tarif par personne x (1 + accompagnateurs). Voir README 2.5.
     */
    public function amountFor(int $companions): int
    {
        return $this->price_per_person * (1 + $companions);
    }

    /**
     * Get the number of seats currently occupied : confirmed registrations, plus held ones
     * whose countdown has not expired (README 2.2). Sums `party_size`, not registration count :
     * un accompagnateur occupe une place comme le participant.
     */
    public function occupiedSeats(): int
    {
        return (int) $this->registrations()->occupyingSeats()->sum('party_size');
    }

    /**
     * Get the number of seats held by confirmed registrations alone.
     *
     * Sert le second declencheur de purge (README 2.4) : capacite atteinte par les seules
     * confirmees, sans attendre l'echeance.
     */
    public function confirmedSeats(): int
    {
        return (int) $this->registrations()
            ->where('status', RegistrationStatus::Confirmed)
            ->sum('party_size');
    }

    /**
     * Get the amount collected so far, in francs CFA : the sum due by confirmed registrations.
     *
     * Une inscription n'est confirmee qu'apres validation de sa preuve : c'est ce que
     * l'organisation a effectivement verifie avoir recu (README 1, aucun encaissement dans
     * l'application).
     */
    public function collectedAmount(): int
    {
        return (int) $this->registrations()
            ->where('status', RegistrationStatus::Confirmed)
            ->sum('amount_due');
    }

    /**
     * Get the seats still available to a guest.
     *
     * Places disponibles = capacite - places CONFIRMED - places HELD non expirees (README 2.2).
     */
    public function remainingSeats(): int
    {
        return max(0, $this->capacity() - $this->occupiedSeats());
    }

    /**
     * Determine whether the event is full.
     *
     * « Complet » n'est pas un statut stocke : il se calcule, sinon il faudrait que quelqu'un
     * pense a le mettre a jour.
     */
    public function isFull(): bool
    {
        return $this->remainingSeats() === 0;
    }

    /**
     * Determine whether a guest may still register.
     */
    public function acceptsRegistrations(): bool
    {
        return $this->status->acceptsRegistrations()
            && ! $this->isFull()
            && ! $this->registrationDeadlineHasPassed()
            && ! (Tenant::current()?->isSuspended() ?? false);
    }

    /**
     * Determine whether the registration deadline has passed.
     */
    public function registrationDeadlineHasPassed(): bool
    {
        return $this->registration_deadline !== null
            && $this->registration_deadline->isPast();
    }

    /**
     * Get the public address of the event, under the subdomain of its organisation.
     *
     * Le schema et le port viennent de `app.url`, pas du domaine des liens publics : ce
     * dernier n'est qu'un hote, compare tel quel au header Host par `Route::domain()`.
     */
    public function publicUrl(): ?string
    {
        $tenant = Tenant::current();

        if ($this->public_token === null || $tenant?->subdomain === null) {
            return null;
        }

        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $domain = $tenant->subdomain.'.'.config('convive.public_domain');

        return "{$scheme}://{$domain}{$port}/e/{$this->public_token}";
    }

    /**
     * Determine whether the event may be published on a public link.
     *
     * L'identite legale de l'organisation en fait partie : un recu emis sans raison sociale ni
     * numero de contribuable n'a aucune valeur.
     */
    public function isReadyToPublish(): bool
    {
        return (Tenant::current()?->isReadyToPublish() ?? false)
            && $this->capacity() > 0
            && $this->starts_at !== null
            && $this->paymentAccounts()->publiclyVisible()->exists();
    }

    /**
     * Determine whether a public link has ever been handed out.
     *
     * Des qu'un lien est distribue, le sous-domaine de l'organisation se fige : le changer
     * casserait des liens deja entre les mains des invites.
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Determine whether this event is announced on the product site's showcase (CLAUDE.md,
     * « Annonce sur le site produit »).
     *
     * Independant de la cloture : retirer l'annonce reste possible a tout moment, y compris
     * apres publication, et cloturer un evenement ne le retire pas de la vitrine tout seul.
     * L'organisateur garde la main sur sa visibilite.
     */
    public function isAnnounced(): bool
    {
        return $this->announced_at !== null;
    }

    /**
     * Give the event its public token, once.
     *
     * Le jeton ne tourne pas : il est l'adresse de l'evenement pour tous ceux qui l'ont recue.
     */
    public function ensurePublicToken(): string
    {
        if ($this->public_token === null) {
            $this->public_token = Str::random(self::PublicTokenBytes * 2);
            $this->save();
        }

        return $this->public_token;
    }

    /**
     * Get (creating on first use) the Ed25519 key pair that signs this event's tickets
     * (README 2.8, SECURITY.md C2).
     *
     * Generee paresseusement, comme `public_token` : `table_count` et la date peuvent encore
     * changer avant le premier billet emis, la cle n'a pas besoin d'exister avant.
     *
     * `qr_secret_key` chiffree au repos (voir `casts()`) : un palliatif honnete en attendant le
     * KMS ou le HSM que SECURITY.md demande pour la production (voir la migration qui pose ces
     * colonnes), pas une pretention a l'egaler.
     *
     * @return array{public: string, secret: string, version: int}
     */
    public function ensureSigningKeyPair(): array
    {
        if ($this->qr_public_key === null || $this->qr_secret_key === null) {
            $keyPair = sodium_crypto_sign_keypair();

            $this->qr_public_key = base64_encode(sodium_crypto_sign_publickey($keyPair));
            $this->qr_secret_key = base64_encode(sodium_crypto_sign_secretkey($keyPair));
            $this->save();
        }

        return [
            'public' => $this->qr_public_key,
            'secret' => $this->qr_secret_key,
            'version' => $this->qr_key_version,
        ];
    }

    /**
     * Get the moment after which this event's tickets stop granting entry (SECURITY.md C2), or
     * null while the event has no date.
     */
    public function ticketValidUntil(): ?CarbonImmutable
    {
        return $this->starts_at?->addHours((int) config('convive.tickets.valid_hours_after_start'));
    }

    /**
     * Replace this event's signing key pair, invalidating every QR token signed so far
     * (SECURITY.md C2, rotation after a suspected leak).
     */
    public function rotateSigningKeyPair(): void
    {
        $keyPair = sodium_crypto_sign_keypair();

        $this->qr_public_key = base64_encode(sodium_crypto_sign_publickey($keyPair));
        $this->qr_secret_key = base64_encode(sodium_crypto_sign_secretkey($keyPair));
        $this->qr_key_version++;
        $this->save();
    }

    /**
     * Scope the query to the events whose public link answers.
     *
     * @param  Builder<Event>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * Scope the query to the events whose doors may open today : published, not closed, and either
     * starting today or already running (a soiree begun the day before stays reachable after
     * midnight).
     *
     * @param  Builder<Event>  $query
     */
    public function scopeCheckInToday(Builder $query): void
    {
        $query->published()
            ->where('status', '!=', EventStatus::Closed)
            ->where(fn (Builder $query) => $query
                ->where('status', EventStatus::Ongoing)
                ->orWhereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()]));
    }

    /**
     * Scope the query to the order the operator expects on the event list.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('starts_at is null')->orderByDesc('starts_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'purge_at' => 'datetime',
            'seats_low_alerted_at' => 'datetime',
            'purge_notice_sent_at' => 'datetime',
            'invitations_send_at' => 'datetime',
            'published_at' => 'datetime',
            'announced_at' => 'datetime',
            'table_count' => 'integer',
            'seats_per_table' => 'integer',
            'price_per_person' => 'integer',
            'companion_limit' => 'integer',
            'hold_duration_minutes' => 'integer',
            'qr_secret_key' => 'encrypted',
            'qr_key_version' => 'integer',
            'reminder_j7_enabled' => 'boolean',
            'reminder_j2_enabled' => 'boolean',
            'reminder_j1_enabled' => 'boolean',
            'reminder_day_of_enabled' => 'boolean',
            'rule_scheduled_send' => 'boolean',
            'rule_auto_seating' => 'boolean',
            'rule_allow_without_proof' => 'boolean',
            'rule_proof_legibility' => 'boolean',
            'rule_purge_on_exhaustion' => 'boolean',
            'rule_temporary_hold' => 'boolean',
            'rule_phone_verification' => 'boolean',
        ];
    }
}
