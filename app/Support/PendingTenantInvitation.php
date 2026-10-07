<?php

namespace App\Support;

use App\Models\TenantInvitation;
use Illuminate\Http\Request;

/**
 * L'invitation d'equipe que la personne a suivie depuis son courriel, gardee en session pendant
 * tout le parcours (inscription ou connexion), jusqu'a ce qu'elle choisisse quoi en faire
 * (decision du proprietaire du projet, 2026-10-07). Sans elle, l'invitation se perdait a la
 * connexion et la personne ne savait plus ce qu'elle etait venue faire.
 *
 * Seul le code est garde ; l'invitation est relue a chaque fois, pour qu'une invitation annulee,
 * acceptee ou expiree entre-temps ne soit plus proposee.
 */
final class PendingTenantInvitation
{
    public const SessionKey = 'tenant_invitation';

    /**
     * Find the invitation still open behind the given code.
     */
    public static function find(?string $code): ?TenantInvitation
    {
        if ($code === null || $code === '') {
            return null;
        }

        return TenantInvitation::query()
            ->with('tenant')
            ->where('code', $code)
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->first();
    }

    /**
     * Remember the invitation the person arrived with, when it is still open.
     */
    public static function remember(Request $request, ?string $code): ?TenantInvitation
    {
        $invitation = self::find($code);

        if ($invitation !== null) {
            $request->session()->put(self::SessionKey, $invitation->code);
        }

        return $invitation;
    }

    /**
     * Get the invitation kept in the session, while it is still open.
     */
    public static function current(Request $request): ?TenantInvitation
    {
        $code = $request->session()->get(self::SessionKey);

        return self::find(is_string($code) ? $code : null);
    }

    /**
     * Forget the invitation : the person accepted it, declined it or chose to set it aside.
     */
    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SessionKey);
    }
}
