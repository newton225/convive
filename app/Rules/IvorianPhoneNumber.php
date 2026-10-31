<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Le telephone d'un invite sur le parcours public : numero ivoirien a dix chiffres seulement (decision du
 * proprietaire du projet, 2026-10-31, qui remplace l'ouverture a tout pays du 2026-09-29). Les comptes de
 * l'equipe gardent `GuestPhoneNumber`, ouvert aux autres pays.
 */
class IvorianPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhoneNumber::normalizeIvorian($value) === null) {
            $fail(__('guest.registration.errors.phone_ivorian_invalid'));
        }
    }
}
