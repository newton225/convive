<?php

namespace App\Http\Controllers\Public;

use App\Enums\BrandFile;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Inscription supprimee » (README ecran 11) : le message que voit un invite dont le dossier a
 * ete purge, a l'echeance ou a l'epuisement des places (README 2.4).
 *
 * Deliberement generique : la page ne dit rien d'une inscription precise, seulement de
 * l'evenement, dont le lien public est deja connu de qui arrive ici. Un lien de reprise
 * inexistant et un lien purge restent indiscernables (404, CLAUDE.md, « Securite ») tant qu'aucune
 * trace de purge n'est conservee : c'est au serveur, plus tard, de rediriger ici.
 */
class RegistrationDeletedController extends Controller
{
    public function __invoke(string $token): Response
    {
        $tenant = Tenant::current();
        abort_if(! $tenant, 404);

        $event = Event::where('public_token', $token)->first();
        abort_if(! $event || ! $event->isPublished(), 404);

        return Inertia::render('public/registration-deleted', [
            'token' => $token,
            'event' => [
                'name' => $event->name,
                'acceptsRegistrations' => $event->acceptsRegistrations(),
                'isFull' => $event->isFull(),
            ],
            'tenant' => [
                'name' => $tenant->name,
                'displayName' => $tenant->branding->display_name ?? $tenant->name,
                'colors' => $tenant->brandingOrCreate()->colors(),
                'logoUrl' => $tenant->branding?->brandFileUrl(BrandFile::Logo),
            ],
        ]);
    }
}
