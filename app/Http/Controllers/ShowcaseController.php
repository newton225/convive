<?php

namespace App\Http\Controllers;

use App\Models\ShowcaseEvent;
use App\Support\ListPage;
use App\Support\Search\UnaccentedSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
 *
 * Paginee et cherchee par le serveur (TODO du 2026-10-07, point 11) : le visiteur ne recoit que la
 * page affichee.
 */
class ShowcaseController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $search = trim((string) $request->input('filter.search', ''));

        $events = ListPage::of(
            ShowcaseEvent::recentlyAnnounced()
                ->when($search !== '', fn (Builder $query) => UnaccentedSearch::apply($query, ['name', 'organisation_name'], $search)),
            $request,
            ListPage::CardsPerPage,
        );

        return Inertia::render('showcase', [
            // Page commerciale mesuree, comme l'accueil (README, « Mesure d'audience »).
            'analyticsId' => config('services.google_analytics.measurement_id'),
            'events' => $events->getCollection()
                ->map(fn (ShowcaseEvent $event) => [
                    'name' => $event->name,
                    'organisationName' => $event->organisation_name,
                    'startsAt' => $event->starts_at?->toISOString(),
                    'publicUrl' => $event->public_url,
                    'visualUrl' => $event->visualUrl(),
                ])
                ->values()
                ->all(),
            'meta' => ListPage::meta($events),
            'filters' => ['search' => $search !== '' ? $search : null],
            'hasEvents' => ShowcaseEvent::query()->exists(),
        ]);
    }
}
