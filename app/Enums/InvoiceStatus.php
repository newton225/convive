<?php

namespace App\Enums;

/**
 * L'etat d'une facture d'abonnement.
 */
enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Failed = 'failed';
    case Void = 'void';

    public function label(): string
    {
        return __("billing.invoice_statuses.{$this->value}");
    }
}
