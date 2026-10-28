<?php

namespace App\Enums;

/**
 * Pourquoi le tresorier rejette une preuve de paiement : catalogue ferme, choisi dans une liste,
 * que l'invite lit sur la page de son dossier (decision du 2026-10-16). Les trois premiers sont les
 * regles de controle annoncees a l'invite sur son formulaire (`guest.proof.help`). `Other` exige une
 * precision ecrite.
 */
enum ProofRejectionReason: string
{
    case EditedCapture = 'edited_capture';
    case IncompleteAmount = 'incomplete_amount';
    case SplitPayment = 'split_payment';
    case Unreadable = 'unreadable';
    case IncompleteCapture = 'incomplete_capture';
    case Other = 'other';

    public function label(): string
    {
        return __("proofs.rejection.reasons.{$this->value}");
    }

    public function requiresNote(): bool
    {
        return $this === self::Other;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $reason) => $reason->value, self::cases());
    }
}
