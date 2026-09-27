<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Nom d'organisation pour les emails du parcours invite aux couleurs de marque (CLAUDE.md,
 * « Envois programmes et rappels »). A appeler synchronement, a l'interieur de la tenancy
 * active qui dispatche la notification : `App\Mail\GuestNotificationMail` peut s'executer plus
 * tard sur un travailleur de file, sans cette garantie.
 */
class GuestNotificationBranding
{
    public static function organisationName(): string
    {
        $tenant = Tenant::current();

        // `Tenant::current()` est declare `?self` : PHPStan infere ici un type non nullable a
        // partir du corps de la methode et signale l'operateur `?->` comme superflu, mais le
        // contrat de la methode reste nullable (aucune tenancy active), donc la garde reste.
        return $tenant?->branding?->display_name ?? $tenant?->name ?? (string) config('app.name');
    }
}
