<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentTenant;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    use RedirectsToCurrentTenant;

    /**
     * README ecran 2 : la creation d'un espace redirige vers le formulaire d'organisation
     * (ecran 14), pas vers le tableau de bord. L'espace personnel cree a l'inscription n'a ni
     * identite legale ni sous-domaine : c'est precisement ce que cet ecran fait saisir avant
     * qu'un evenement puisse etre publie (`Tenant::isReadyToPublish()`).
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 201);
        }

        $tenant = $this->currentTenant($request);

        // Inscrite pour rejoindre une organisation, la personne n'en a pas encore : elle accepte
        // d'abord l'invitation, depuis son accueil (TODO du 2026-10-07).
        if ($tenant === null) {
            return redirect()->route('invitations.index');
        }

        return redirect()->intended(route('tenants.organisation.edit', $tenant));
    }
}
