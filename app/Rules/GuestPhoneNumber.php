<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un numero de telephone ivoirien, quelle que soit son ecriture (voir `PhoneNumber`).
 */
class IvorianPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhoneNumber::normalize($value) === null) {
            $fail(__('guest.registration.errors.phone_invalid'));
        }
    }
}
