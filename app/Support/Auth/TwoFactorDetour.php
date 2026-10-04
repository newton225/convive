<?php

namespace App\Support\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Le detour par l'ecran de securite pour activer la double authentification : pourquoi on y est
 * envoye, et ou revenir une fois qu'elle est activee. Garde en session, parce que l'activation
 * recharge l'ecran plusieurs fois sans les parametres d'origine.
 *
 * L'adresse de retour est toujours construite ici, a partir d'une organisation dont la personne est
 * membre, jamais recue telle quelle : un lien ne doit pas pouvoir renvoyer ailleurs.
 */
class TwoFactorDetour
{
    public const SessionKey = 'two_factor_detour';

    public const PaymentAccounts = 'payment_accounts';

    public const Profile = 'profile';

    /**
     * Remember the detour asked by a link of the security screen (`?for=payment-accounts&tenant=`).
     */
    public static function rememberFromQuery(Request $request): void
    {
        $user = $request->user();
        $slug = $request->query('tenant');

        if ($request->query('for') !== 'payment-accounts' || ! is_string($slug) || ! $user instanceof User) {
            return;
        }

        $tenant = Tenant::where('slug', $slug)->first();

        if ($tenant === null || ! $user->belongsToTenant($tenant)) {
            return;
        }

        self::remember($request, self::PaymentAccounts, route('tenants.payment-accounts.index', $tenant, absolute: false));
    }

    public static function remember(Request $request, string $reason, string $returnUrl): void
    {
        $request->session()->put(self::SessionKey, ['reason' => $reason, 'returnUrl' => $returnUrl]);
    }

    /**
     * The detour to explain on the security screen. Once two-factor authentication is on, it is
     * shown one last time (with the way back) and then forgotten.
     *
     * @return array{reason: string, returnUrl: string}|null
     */
    public static function forDisplay(Request $request, bool $twoFactorEnabled): ?array
    {
        $detour = $twoFactorEnabled
            ? $request->session()->pull(self::SessionKey)
            : $request->session()->get(self::SessionKey);

        return is_array($detour) ? $detour : null;
    }
}
