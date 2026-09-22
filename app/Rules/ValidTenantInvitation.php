<?php

namespace App\Rules;

use App\Models\TenantInvitation;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTenantInvitation implements ValidationRule
{
    public function __construct(protected ?User $user)
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof TenantInvitation || ! $this->user instanceof User) {
            $fail(__('tenants.errors.invitation_wrong_email'));

            return;
        }

        if ($value->isAccepted()) {
            $fail(__('tenants.errors.invitation_already_accepted'));

            return;
        }

        if ($value->isExpired()) {
            $fail(__('tenants.errors.invitation_expired'));

            return;
        }

        if (strtolower($value->email) !== strtolower($this->user->email)) {
            $fail(__('tenants.errors.invitation_wrong_email'));
        }
    }
}
