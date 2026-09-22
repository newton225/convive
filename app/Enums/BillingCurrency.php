<?php

namespace App\Enums;

/**
 * Les devises de facturation de l'abonnement : le franc CFA, l'euro et le dollar (decision du
 * proprietaire, 2026-09-21). Les montants se stockent toujours dans la plus petite unite de la
 * devise : le franc CFA n'a pas de decimale (un entier est la representation exacte), l'euro et
 * le dollar se comptent en centimes.
 */
enum BillingCurrency: string
{
    case Xof = 'XOF';
    case Eur = 'EUR';
    case Usd = 'USD';

    /**
     * Get the currencies offered, in the order of `convive.billing.currencies`.
     *
     * @return array<int, self>
     */
    public static function enabled(): array
    {
        $enabled = array_values(array_filter(array_map(
            fn (string $code) => self::tryFrom(strtoupper($code)),
            (array) config('convive.billing.currencies'),
        )));

        return $enabled === [] ? [self::Xof] : $enabled;
    }

    /**
     * Get the currency used until the tenant picks another one : the first one offered.
     */
    public static function default(): self
    {
        return self::enabled()[0];
    }

    /**
     * Get the code Stripe expects : minuscules.
     */
    public function stripeCode(): string
    {
        return strtolower($this->value);
    }

    /**
     * Get how many minor units make one major unit. Zero-decimal for the CFA franc.
     */
    public function minorUnitsPerMajor(): int
    {
        return $this === self::Xof ? 1 : 100;
    }

    /**
     * Get the `plans` column holding the monthly price in this currency.
     */
    public function planColumn(): string
    {
        return match ($this) {
            self::Xof => 'monthly_price',
            self::Eur => 'monthly_price_eur',
            self::Usd => 'monthly_price_usd',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function enabledValues(): array
    {
        return array_map(fn (self $currency) => $currency->value, self::enabled());
    }
}
