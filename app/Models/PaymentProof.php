<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use App\Support\PerceptualHash;
use Database\Factories\PaymentProofFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Une preuve de paiement deposee par un invite (README ecran 6, 2.1, 2.9). Vit dans la base du
 * locataire (voir CLAUDE.md, « Multi-locataire ») : aucune colonne `tenant_id`.
 *
 * @property int $id
 * @property int $registration_id
 * @property int $payment_account_id
 * @property PaymentChannel $channel
 * @property string|null $reference
 * @property int $amount_declared
 * @property string|null $perceptual_hash
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Registration $registration
 * @property-read PaymentAccount $paymentAccount
 */
#[Fillable(['registration_id', 'payment_account_id', 'channel', 'reference', 'amount_declared', 'perceptual_hash', 'idempotency_key'])]
class PaymentProof extends Model implements HasMedia
{
    /** @use HasFactory<PaymentProofFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les connexions
     * SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Nom de la collection portant la capture du recu. Un seul exemplaire par preuve : une
     * resoumission cree une nouvelle preuve, pas un remplacement de fichier sur celle-ci.
     */
    public const ReceiptCollection = 'receipt';

    /**
     * Duree de validite de l'URL signee d'un recu. Quelques minutes, pas d'heures (SECURITY.md
     * H2) : ce sont des donnees financieres personnelles, contrairement aux fichiers de marque.
     */
    public const ReceiptUrlMinutes = 5;

    /**
     * Ecart maximal, en bits, entre deux empreintes pour les considerer comme la meme capture.
     * Une empreinte perceptuelle tolere une recompression ou un leger recadrage : l'exiger
     * identique bit a bit manquerait la plupart des doublons reels.
     */
    public const DuplicateHashThreshold = 4;

    /**
     * Le SVG est absent volontairement (CLAUDE.md, « Fichiers deposes » ; SECURITY.md H1) : il
     * peut porter du script, et rien ne le justifie pour la capture d'un recu.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ReceiptCollection)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    /**
     * Get a signed, expiring URL for the receipt capture, or null when it is not yet stored.
     */
    public function receiptUrl(int $minutes = self::ReceiptUrlMinutes): ?string
    {
        $media = $this->getFirstMedia(self::ReceiptCollection);

        return $media instanceof Media
            ? $media->getTemporaryUrl(now()->addMinutes($minutes))
            : null;
    }

    /**
     * Determine whether another proof carries the same transaction reference (README 2.9).
     * Une reference vide (versement en especes) n'est jamais consideree comme un doublon.
     */
    public function hasDuplicateReference(): bool
    {
        if ($this->reference === null) {
            return false;
        }

        return self::query()
            ->where('reference', $this->reference)
            ->whereKeyNot($this->getKey())
            ->exists();
    }

    /**
     * Determine whether another proof's receipt looks like the same capture (README 2.9),
     * within `DuplicateHashThreshold` bits of the perceptual hash.
     */
    public function hasDuplicateImage(): bool
    {
        if ($this->perceptual_hash === null) {
            return false;
        }

        return self::query()
            ->whereKeyNot($this->getKey())
            ->whereNotNull('perceptual_hash')
            ->get(['id', 'perceptual_hash'])
            ->contains(fn (self $other) => PerceptualHash::hammingDistance(
                $this->perceptual_hash,
                $other->perceptual_hash,
            ) <= self::DuplicateHashThreshold);
    }

    /**
     * Determine whether this proof's reference is absent from the statements imported for its
     * event (README 2.9).
     *
     * Sans releve importe pour l'evenement, ou pour un versement en especes sans reference, le
     * signal ne s'allume jamais : l'absence d'un releve n'est pas la preuve qu'une reference
     * manque.
     */
    public function referenceMissingFromStatement(): bool
    {
        if ($this->reference === null || ! $this->statementLines()->exists()) {
            return false;
        }

        return ! $this->statementLinesWithSameReference()->exists();
    }

    /**
     * Determine whether the statement line carrying this proof's reference shows another amount
     * than the one declared (README 2.9). Sans ligne portant cette reference, c'est
     * `referenceMissingFromStatement()` qui signale, pas celui-ci.
     */
    public function hasStatementAmountMismatch(): bool
    {
        if ($this->reference === null) {
            return false;
        }

        $amounts = $this->statementLinesWithSameReference()->get()->pluck('amount');

        return $amounts->isNotEmpty() && ! $amounts->contains($this->amount_declared);
    }

    /**
     * @return Builder<StatementLine>
     */
    private function statementLines(): Builder
    {
        return StatementLine::query()->whereHas(
            'statementImport',
            fn (Builder $query) => $query->where('event_id', $this->registration->event_id),
        );
    }

    /**
     * @return Builder<StatementLine>
     */
    private function statementLinesWithSameReference(): Builder
    {
        return $this->statementLines()->whereRaw(
            'UPPER(TRIM(reference)) = ?',
            [mb_strtoupper(trim((string) $this->reference))],
        );
    }

    /**
     * Get the registration this proof was submitted for.
     *
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * Get the payment account the guest claims to have paid into.
     *
     * @return BelongsTo<PaymentAccount, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /**
     * Scope the query to the most recently submitted proofs first.
     *
     * @param  Builder<PaymentProof>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'amount_declared' => 'integer',
        ];
    }
}
