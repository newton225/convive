<?php

namespace App\Contracts;

use RuntimeException;

/**
 * Levee quand on demande une operation de paiement alors qu'aucun fournisseur n'est branche.
 */
class BillingNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No subscription billing provider is configured.');
    }
}
