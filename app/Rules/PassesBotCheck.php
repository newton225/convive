<?php

namespace App\Rules;

use App\Support\BotCheck;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Le jeton du widget anti-robot doit etre accepte par Cloudflare (`BotCheck`).
 */
class PassesBotCheck implements ValidationRule
{
    public function __construct(private readonly ?string $ip)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! BotCheck::passes($value, $this->ip)) {
            $fail(__('guest.registration.errors.bot_check'));
        }
    }
}
