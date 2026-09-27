<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Les sessions ouvertes d'un membre (SECURITY.md, « Deconnexion et sessions »), lues dans la table
 * `sessions` de la base centrale : une session du back-office demarre et s'enregistre sur le domaine
 * central, jamais dans la base d'un locataire (voir `EnsureTenantMembership`, qui termine la
 * tenancy avant la sauvegarde de session).
 *
 * L'identifiant de session ne quitte jamais le serveur : c'est le secret qui ouvre le compte.
 */
final class ConnectedDevices
{
    /**
     * @return array<int, array{browser: string|null, platform: string|null, mobile: bool, ipAddress: string|null, lastActiveAt: string, isCurrent: bool}>
     */
    public static function for(User $user, string $currentSessionId): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return self::sessions()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session) => [
                ...UserAgentSummary::from($session->user_agent),
                'ipAddress' => $session->ip_address,
                'lastActiveAt' => CarbonImmutable::createFromTimestamp((int) $session->last_activity)->toISOString(),
                'isCurrent' => hash_equals((string) $session->id, $currentSessionId),
            ])
            ->all();
    }

    /**
     * Delete every other session of the user, returning how many were closed.
     *
     * Complete `Auth::logoutOtherDevices()`, qui ne fait que changer l'empreinte du mot de passe :
     * les autres sessions ne seraient rejetees qu'a leur prochaine requete, et resteraient dans la
     * liste jusque-la.
     */
    public static function closeOthers(User $user, string $currentSessionId): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return self::sessions()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    private static function sessions(): Builder
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table((string) config('session.table', 'sessions'));
    }
}
