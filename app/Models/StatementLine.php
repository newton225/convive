<?php

namespace App\Models;

use App\Enums\ReconciliationOutcome;
use Database\Factories\StatementLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une ligne d'un releve importe et son issue de rapprochement (README 2.10, ecran 19). Vit dans
 * la base du locataire (voir CLAUDE.md, « Multi-locataire »).
 *
 * `resolved_at` distingue « pas encore regardee » de « vue, sans correspondance ».
 * `resolved_by_user_id` reference `User`, qui vit dans la base centrale : pas de cle etrangere.
 *
 * @property int $id
 * @property int $statement_import_id
 * @property int $line_number
 * @property Carbon $occurred_on
 * @property string|null $reference
 * @property string $issuer
 * @property int $amount
 * @property ReconciliationOutcome $outcome
 * @property int|null $matched_registration_id
 * @property int|null $matched_payment_proof_id
 * @property int|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StatementImport $statementImport
 * @property-read Registration|null $matchedRegistration
 * @property-read PaymentProof|null $matchedPaymentProof
 */
#[Fillable([
    'statement_import_id', 'line_number', 'occurred_on', 'reference', 'issuer', 'amount',
    'outcome', 'matched_registration_id', 'matched_payment_proof_id',
    'resolved_by_user_id', 'resolved_at',
])]
class StatementLine extends Model
{
    /** @use HasFactory<StatementLineFactory> */
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the statement this line belongs to.
     *
     * @return BelongsTo<StatementImport, $this>
     */
    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(StatementImport::class);
    }

    /**
     * Get the registration this line was matched to, automatically or by hand.
     *
     * @return BelongsTo<Registration, $this>
     */
    public function matchedRegistration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'matched_registration_id');
    }

    /**
     * Get the payment proof this line was matched to, if the registration carries one.
     *
     * @return BelongsTo<PaymentProof, $this>
     */
    public function matchedPaymentProof(): BelongsTo
    {
        return $this->belongsTo(PaymentProof::class, 'matched_payment_proof_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'occurred_on' => 'date',
            'amount' => 'integer',
            'outcome' => ReconciliationOutcome::class,
            'resolved_at' => 'datetime',
        ];
    }
}
