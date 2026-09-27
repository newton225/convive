<?php

namespace App\Http\Controllers;

use App\Models\ShowcaseEvent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La vitrine des evenements a la une (CLAUDE.md, « Annonce sur le site produit »), domaine
 * central, aucun sous-domaine.
 *
 * Lit uniquement la table centrale `showcase_events`, jamais les bases des locataires : boucler
 * sur chaque organisation et initialiser sa tenancy a chaque visiteur anonyme ne passerait pas a
 * l'echelle (CLAUDE.md, « Multi-locataire »), le meme risque qu'une tache planifiee non bornee,
 * ici sur le chemin critique d'une page publique.
 */
class ShowcaseController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('showcase', [
            'events' => ShowcaseEvent::recentlyAnnounced()->get()
                ->map(fn (ShowcaseEvent $event) => [
                    'name' => $event->name,
                    'organisationName' => $event->organisation_name,
                    'startsAt' => $event->starts_at?->toISOString(),
                    'publicUrl' => $event->public_url,
                    'visualUrl' => $event->visualUrl(),
                ])
                ->all(),
        ]);
    }
}
