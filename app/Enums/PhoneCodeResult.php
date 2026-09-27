<?php

namespace App\Enums;

/**
 * Issue de la saisie d'un code de verification du telephone.
 */
enum PhoneCodeResult: string
{
    case Verified = 'verified';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case TooManyAttempts = 'too_many_attempts';

    /**
     * Get the message shown under the code field when the code is refused.
     */
    public function message(): string
    {
        return __("guest.phone_verification.errors.{$this->value}");
    }
}
