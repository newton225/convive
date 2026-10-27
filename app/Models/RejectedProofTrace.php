<?php

namespace App\Models;

use App\Enums\ProofRejectionReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ce qui reste d'une preuve rejetee apres la purge de son dossier : la reference de la transaction,
 * l'empreinte de la capture et le motif du rejet, sans nom, numero ni image. Elle sert uniquement a
 * reconnaitre une reference ou une capture deja rejetee si elle est redeposee (README 2.9). Vit dans la
 * base du locataire, ecriture seule, supprimee avec son evenement.
 *
 * @property int $id
 * @property int $event_id
 * @property string|null $reference
 * @property string|null $reference_key
 * @property string|null $perceptual_hash
 * @property ProofRejectionReason|null $rejection_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property-read Event $event
 */
#[Fillable(['event_id', 'reference', 'reference_key', 'perceptual_hash', 'rejection_reason', 'submitted_at'])]
class RejectedProofTrace extends Model
{
    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'rejection_reason' => ProofRejectionReason::class,
            'submitted_at' => 'datetime',
        ];
    }
}
