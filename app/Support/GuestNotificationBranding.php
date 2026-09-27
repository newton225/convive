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

        // `??` couvre deja une organisation ou une marque absentes (aucune tenancy active) : il
        // n'evalue pas la suite d'une chaine de proprietes dont un maillon est nul.
        return $tenant->branding->display_name ?? $tenant->name ?? (string) config('app.name');
    }
}
