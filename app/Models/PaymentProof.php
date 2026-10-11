<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use App\Support\PerceptualHash;
use Database\Factories\PaymentProofFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
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
 * @property string|null $guest_note
 * @property string|null $perceptual_hash
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Registration $registration
 * @property-read PaymentAccount $paymentAccount
 */
#[Fillable(['registration_id', 'payment_account_id', 'channel', 'reference', 'guest_note', 'perceptual_hash', 'idempotency_key'])]
class PaymentProof extends Model implements HasMedia
{
    /** @use HasFactory<PaymentProofFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * Les signaux deja calcules en lot par `preloadSignals()`, ou null : les methodes de signal
     * interrogent alors la base elles-memes (une preuve isolee).
     *
     * @var array{duplicateReference: bool, duplicateImageIds: array<int, int>, referenceMissingFromStatement: bool, statementAmountMismatch: bool}|null
     */
    private ?array $signals = null;

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
        // Disque distinct de `tenant_media` (SECURITY.md H1) : une capture deposee par un
        // invite inconnu ne partage pas le meme espace que les fichiers de marque du
        // locataire, et se sert en piece jointe plutot qu'en affichage direct
        // (`AppServiceProvider::configurePaymentProofDisk()`).
        $this->addMediaCollection(self::ReceiptCollection)
            ->useDisk('payment_proofs')
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

        if ($this->signals !== null) {
            return $this->signals['duplicateReference'];
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
        return $this->duplicateImageProofIds()->isNotEmpty();
    }

    /**
     * Get the other proofs whose receipt looks like the same capture, oldest first, with what the
     * treasurer needs to compare them : registration and event. Tous evenements confondus : une
     * capture reutilisee d'un evenement a l'autre est la fraude la plus simple.
     *
     * @return Collection<int, self>
     */
    public function duplicateImageProofs(): Collection
    {
        $ids = $this->duplicateImageProofIds();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return self::query()
            ->whereKey($ids)
            ->with(['registration.event', 'media'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return SupportCollection<int, int>
     */
    private function duplicateImageProofIds(): SupportCollection
    {
        if ($this->perceptual_hash === null) {
            return new SupportCollection;
        }

        if ($this->signals !== null) {
            return collect($this->signals['duplicateImageIds']);
        }

        return self::query()
            ->whereKeyNot($this->getKey())
            ->whereNotNull('perceptual_hash')
            ->get(['id', 'perceptual_hash'])
            ->filter(fn (self $other) => PerceptualHash::hammingDistance(
                $this->perceptual_hash,
                $other->perceptual_hash,
            ) <= self::DuplicateHashThreshold)
            ->map(fn (self $other) => $other->id)
            ->values()
            ->toBase();
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
        if ($this->reference === null) {
            return false;
        }

        if ($this->signals !== null) {
            return $this->signals['referenceMissingFromStatement'];
        }

        if (! $this->statementLines()->exists()) {
            return false;
        }

        return ! $this->statementLinesWithSameReference()->exists();
    }

    /**
     * Determine whether the statement line carrying this proof's reference shows another amount
     * than the one the registration owes (README 2.9). L'invite ne declare plus de montant
     * (decision du 2026-09-29) : le releve se compare a ce qui est du, pas a ce qui a ete tape. Sans ligne portant cette reference, c'est
     * `referenceMissingFromStatement()` qui signale, pas celui-ci.
     */
    public function hasStatementAmountMismatch(): bool
    {
        if ($this->reference === null) {
            return false;
        }

        if ($this->signals !== null) {
            return $this->signals['statementAmountMismatch'];
        }

        $amounts = $this->statementLinesWithSameReference()->get()->pluck('amount');

        return $amounts->isNotEmpty() && ! $amounts->contains($this->registration->amount_due);
    }

    /**
     * Calcule d'un coup les signaux d'une liste de preuves (file de verification) : au lieu de poser
     * quatre a six requetes par ligne, une requete lit toutes les references et empreintes, une autre
     * les lignes de releve concernees. Mesure par `QueryBudgetTest` : 154 requetes pour une page de
     * 25 preuves avant, un nombre constant apres. Les methodes de signal rendent ensuite ces valeurs.
     *
     * Chaque preuve doit porter sa relation `registration` (deja chargee par la file).
     *
     * @param  array<int, PaymentProof|null>  $proofs
     */
    public static function preloadSignals(array $proofs): void
    {
        $proofs = collect($proofs)->filter()->values();

        if ($proofs->isEmpty()) {
            return;
        }

        $all = self::query()->get(['id', 'reference', 'perceptual_hash']);
        $byReference = $all->whereNotNull('reference')->groupBy('reference');
        $hashed = $all->whereNotNull('perceptual_hash');

        // Les lignes de releve de chaque evenement concerne, pour les references de la page.
        $statement = [];

        foreach ($proofs->groupBy(fn (self $proof) => $proof->registration->event_id) as $eventId => $group) {
            $references = $group->pluck('reference')->filter()
                ->map(fn ($reference) => mb_strtoupper(trim((string) $reference)))->unique()->values()->all();

            $lines = StatementLine::query()
                ->whereHas('statementImport', fn (Builder $query) => $query->where('event_id', $eventId))
                ->get(['reference', 'amount']);

            $statement[$eventId] = [
                'hasLines' => $lines->isNotEmpty(),
                'amounts' => $lines
                    ->filter(fn (StatementLine $line) => in_array(mb_strtoupper(trim((string) $line->reference)), $references, true))
                    ->groupBy(fn (StatementLine $line) => mb_strtoupper(trim((string) $line->reference)))
                    ->map(fn ($grouped) => $grouped->pluck('amount')),
            ];
        }

        foreach ($proofs as $proof) {
            $key = mb_strtoupper(trim((string) $proof->reference));
            $lines = $statement[$proof->registration->event_id];
            $amounts = $lines['amounts']->get($key, collect());

            $proof->signals = [
                'duplicateReference' => $proof->reference !== null
                    && $byReference->get($proof->reference, collect())->contains(fn (self $other) => $other->id !== $proof->id),
                'duplicateImageIds' => $proof->perceptual_hash === null ? [] : $hashed
                    ->filter(fn (self $other) => $other->id !== $proof->id
                        && PerceptualHash::hammingDistance($proof->perceptual_hash, $other->perceptual_hash) <= self::DuplicateHashThreshold)
                    ->map(fn (self $other) => $other->id)->values()->all(),
                'referenceMissingFromStatement' => $proof->reference !== null && $lines['hasLines'] && $amounts->isEmpty(),
                'statementAmountMismatch' => $proof->reference !== null && $amounts->isNotEmpty()
                    && ! $amounts->contains($proof->registration->amount_due),
            ];
        }
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
        ];
    }
}
