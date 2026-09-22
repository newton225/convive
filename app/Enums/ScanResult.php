<?php

namespace App\Enums;

/**
 * Les trois resultats d'un scan a l'entree (README 2.8, ecran 26).
 */
enum ScanResult: string
{
    case Accepted = 'accepted';
    case AlreadyScanned = 'already_scanned';
    case Refused = 'refused';

    public function label(): string
    {
        return __("scan.results.{$this->value}");
    }
}
