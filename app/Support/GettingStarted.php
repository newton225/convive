<?php

namespace App\Support;

use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;

/**
 * La carte « Premiers pas » du tableau de bord : les etapes qui menent une organisation neuve
 * jusqu'a son premier lien public, dans l'ordre ou `Event::isReadyToPublish()` les exige.
 *
 * Chaque etape se coche d'apres les donnees reelles, jamais d'apres un clic : une etape
 * « faite » qui ne le serait pas bloquerait la publication sans que la carte l'explique. A lire
 * dans la tenancy de l'organisation (evenements et comptes vivent dans sa base).
 */
final class GettingStarted
{
    /**
     * @return array{steps: array<int, array{key: string, done: bool}>, completed: int}|null
     */
    public static function for(Tenant $tenant, User $user): ?array
    {
        // Seul qui peut creer un evenement a quelque chose a faire de cette carte.
        if (! $user->hasTenantPermission($tenant, TenantPermission::EventsCreate)) {
            return null;
        }

        $steps = [
            ['key' => 'identity', 'done' => $tenant->isReadyToPublish()],
            ['key' => 'payment_account', 'done' => PaymentAccount::publiclyVisible()->exists()],
            ['key' => 'event', 'done' => Event::query()->exists()],
            ['key' => 'publish', 'done' => Event::published()->exists()],
            ['key' => 'team', 'done' => $tenant->memberships()->count() > 1
                || $tenant->invitations()->whereNull('accepted_at')->exists()],
        ];

        $completed = count(array_filter($steps, fn (array $step) => $step['done']));

        return $completed === count($steps) ? null : ['steps' => $steps, 'completed' => $completed];
    }
}
