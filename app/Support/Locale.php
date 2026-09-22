<?php

namespace App\Support;

final class Locale
{
    public const Default = 'fr';

    /**
     * Get the locales the application ships, keyed by code, valued by native label.
     *
     * @return array<string, string>
     */
    public static function supported(): array
    {
        return [
            'fr' => 'Français',
            'en' => 'English',
        ];
    }

    /**
     * Get the supported locale codes.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::supported());
    }

    /**
     * Determine whether the given locale is supported.
     */
    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::codes(), true);
    }

    /**
     * Reduce arbitrary input to a supported locale, or null.
     */
    public static function sanitize(mixed $locale): ?string
    {
        return self::isSupported($locale) ? $locale : null;
    }
}
