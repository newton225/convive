<?php

namespace App\Actions\Registrations;

use App\Models\RegistrationEvent;

/**
 * Efface l'adresse IP des faits de l'historique des dossiers au bout de douze mois, sur la base
 * active. Le fait lui-meme reste avec son dossier : seule la donnee personnelle la plus fine part.
 */
class PruneRegistrationEventIps
{
    /**
     * @return int le nombre de lignes dont l'adresse a ete effacee
     */
    public function handle(): int
    {
        return RegistrationEvent::query()
            ->whereNotNull('ip_address')
            ->where('created_at', '<', now()->subMonths(RegistrationEvent::IpRetentionMonths))
            ->update(['ip_address' => null]);
    }
}
