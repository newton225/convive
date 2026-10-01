<?php

namespace App\Support\Console;

use App\Enums\ConsoleArea;
use App\Enums\ConsoleProfile;
use App\Models\ConsoleOperator;
use App\Models\User;

/**
 * Qui ouvre la console d'exploitation, et quels ecrans (README section 3). Seule source de verite
 * des portes `console.access` et `console.area`.
 *
 * Deux origines : les adresses de `convive.console.operators`, Fondateurs de depart que rien ne
 * retire depuis un ecran, et la table `console_operators`, que les Fondateurs tiennent a jour.
 * L'adresse compte, pas un compte a part : le groupe de routes exige une adresse verifiee.
 */
class ConsoleAccess
{
    /**
     * Get the console profile of the given user, or null when they are not part of the team.
     */
    public static function profileOf(User $user): ?ConsoleProfile
    {
        $email = strtolower($user->email);

        if (self::isBootstrapFounder($email)) {
            return ConsoleProfile::Founder;
        }

        return ConsoleOperator::where('email', $email)->first()?->profile;
    }

    public static function allows(User $user, ConsoleArea $area): bool
    {
        return self::profileOf($user)?->allows($area) ?? false;
    }

    /**
     * Get the areas the given user may open, as sent to the console menu.
     *
     * @return array<int, string>
     */
    public static function areasOf(User $user): array
    {
        return array_map(
            fn (ConsoleArea $area) => $area->value,
            self::profileOf($user)?->areas() ?? [],
        );
    }

    /**
     * Determine whether the address is one of the founders set in the configuration.
     */
    public static function isBootstrapFounder(string $email): bool
    {
        return in_array(strtolower($email), self::bootstrapFounders(), true);
    }

    /**
     * Get the addresses of the founders set in the configuration.
     *
     * @return array<int, string>
     */
    public static function bootstrapFounders(): array
    {
        return array_values(array_filter((array) config('convive.console.operators'), is_string(...)));
    }

    /**
     * Get the addresses of every member whose profile opens the given area.
     *
     * @return array<int, string>
     */
    public static function emailsAllowedTo(ConsoleArea $area): array
    {
        $profiles = array_filter(
            ConsoleProfile::cases(),
            fn (ConsoleProfile $profile) => $profile->allows($area),
        );

        return array_values(array_unique([
            ...self::bootstrapFounders(),
            ...ConsoleOperator::whereIn('profile', array_map(
                fn (ConsoleProfile $profile) => $profile->value,
                $profiles,
            ))->pluck('email')->all(),
        ]));
    }
}
