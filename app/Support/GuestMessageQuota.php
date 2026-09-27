<?php

namespace App\Support;

use App\Actions\Notifications\SendAlert;
use App\Enums\NotificationType;
use App\Models\MessageUsage;
use App\Models\Tenant;

/**
 * Le passage oblige de tout message envoye a un invite (carte d'invitation, rappels) : verifier le
 * quota mensuel du plan avant, le compter apres (SECURITY.md H5).
 *
 * Sans plafond sur le plan (le cas de tous les plans aujourd'hui), rien n'est jamais bloque, mais
 * tout est compte : l'ecran Abonnement montre l'usage reel le jour ou un plafond sera fixe. Un
 * message refuse n'est pas marque envoye : la tache planifiee le reprendra au mois suivant.
 */
final class GuestMessageQuota
{
    /**
     * Determine whether a guest message may leave now, telling the subscription holders once a
     * month when the quota stops the sending.
     */
    public static function allows(): bool
    {
        $tenant = Tenant::current();

        if ($tenant === null || PlanLimits::for($tenant)->canSendMessage()) {
            return true;
        }

        $usage = MessageUsage::currentMonth();

        if ($usage->quota_alerted_at === null) {
            $usage->update(['quota_alerted_at' => now()]);

            app(SendAlert::class)->toTenantMembers(
                NotificationType::MessageQuotaReached,
                ['plan' => $tenant->plan()->name, 'count' => $usage->count],
                route('tenants.billing.show', $tenant, absolute: false),
            );
        }

        return false;
    }

    /**
     * Count one message sent to a guest this month.
     */
    public static function record(): void
    {
        if (Tenant::current() === null) {
            return;
        }

        MessageUsage::currentMonth()->increment('count');
    }
}
