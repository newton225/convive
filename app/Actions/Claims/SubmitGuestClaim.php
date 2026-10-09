<?php

namespace App\Actions\Claims;

use App\Actions\Notifications\SendAlert;
use App\Enums\ClaimCategory;
use App\Enums\NotificationType;
use App\Models\GuestClaim;
use App\Models\Registration;
use App\Models\Tenant;

/**
 * Enregistre la reclamation d'un invite sur son dossier et previent l'organisation (decision du
 * 2026-10-09). L'invite ne voit jamais les coordonnees de l'organisation : il ecrit ici, et c'est
 * l'organisation qui le rappelle au numero du dossier.
 */
class SubmitGuestClaim
{
    /**
     * @return GuestClaim|null null quand le dossier a deja atteint le nombre de reclamations
     *                         ouvertes : l'invite attend qu'on lui reponde
     */
    public function handle(Registration $registration, ClaimCategory $category, string $message): ?GuestClaim
    {
        if ($registration->claims()->open()->count() >= GuestClaim::MaxOpenPerRegistration) {
            return null;
        }

        $claim = $registration->claims()->create([
            'category' => $category,
            'message' => trim($message),
        ]);

        // Le texte vient d'un inconnu : il n'entre pas dans l'alerte, seulement son auteur.
        app(SendAlert::class)->toTenantMembers(
            NotificationType::ClaimReceived,
            ['name' => $registration->name, 'event' => $registration->event->name],
            route('tenants.events.claims.index', [Tenant::current(), $registration->event], absolute: false),
        );

        return $claim;
    }
}
