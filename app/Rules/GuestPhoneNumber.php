<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Le telephone d'un invite : tout pays, quelle que soit son ecriture, un numero sans indicatif etant
 * lu comme ivoirien (voir `PhoneNumber::normalize`).
 */
class GuestPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhoneNumber::normalize($value) === null) {
            $fail(__('guest.registration.errors.phone_invalid'));
        }
    }
}
