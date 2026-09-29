<?php

namespace App\Data;

use App\Enums\PaymentChannel;
use App\Enums\RefundStatus;
use Carbon\CarbonInterface;
use DomainException;

/**
 * Ce que l'organisation decide du paiement d'une inscription validee qu'elle annule (README
 * 2.11) : a rembourser, rembourse (moyen, date, reference, frais) ou conserve (motif). Les champs
 * qui ne concernent pas le choix restent nuls, pour qu'un ancien choix ne laisse rien derriere
 * lui.
 */
readonly class RefundDecision
{
    private function __construct(
        public RefundStatus $status,
        public ?PaymentChannel $channel = null,
        public ?CarbonInterface $refundedOn = null,
        public ?int $fee = null,
        public ?string $reference = null,
        public ?string $keptReason = null,
    ) {
        //
    }

    public static function due(): self
    {
        return new self(RefundStatus::Due);
    }

    public static function refunded(PaymentChannel $channel, CarbonInterface $refundedOn, int $fee, ?string $reference = null): self
    {
        return new self(RefundStatus::Refunded, $channel, $refundedOn, $fee, $reference);
    }

    public static function kept(string $reason): self
    {
        return new self(RefundStatus::Kept, keptReason: $reason);
    }

    /**
     * Refuse fees that would swallow the whole refund (README 2.11) : rembourser se fait en tout
     * ou rien, des frais egaux au montant paye ne rembourseraient rien.
     *
     * Rejoue ici en plus de la Form Request : une Action appelee hors requete HTTP (commande,
     * test) ne doit pas pouvoir ecrire un remboursement negatif ou nul.
     *
     * @throws DomainException
     */
    public function assertFeeBelow(int $amountPaid): void
    {
        if ($this->fee !== null && ($this->fee < 0 || $this->fee >= $amountPaid)) {
            throw new DomainException('Les frais de transaction doivent rester inferieurs au montant paye.');
        }
    }

    /**
     * Get the registration columns this decision writes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'refund_status' => $this->status,
            'refund_channel' => $this->channel,
            'refunded_on' => $this->refundedOn?->toDateString(),
            'refund_fee' => $this->fee,
            'refund_reference' => $this->reference,
            'refund_kept_reason' => $this->keptReason,
        ];
    }
}
