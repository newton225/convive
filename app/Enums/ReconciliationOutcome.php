<?php

namespace App\Enums;

/**
 * Les quatre issues du rapprochement d'une ligne de releve (README 2.10).
 */
enum ReconciliationOutcome: string
{
    case Matched = 'matched';
    case AmountMismatch = 'amount_mismatch';
    case ApproximateName = 'approximate_name';
    case NoRegistration = 'no_registration';

    /**
     * Get the label shown to the operator.
     */
    public function label(): string
    {
        return __("reconciliation.outcomes.{$this->value}");
    }
}
