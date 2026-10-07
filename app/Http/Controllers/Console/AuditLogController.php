<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\ConsoleActionLog;
use App\Support\ListPage;
use App\Support\Search\UnaccentedSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le journal central (README ecran 33) : les actions de la console, les plus recentes d'abord.
 * Ecriture seule, tenu par `ConsoleJournal`.
 *
 * Pagine, cherche et filtre par le serveur (TODO du 2026-10-07, point 11) : plus de fenetre des
 * dernieres entrees, toutes restent atteignables.
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $search = trim((string) $request->input('filter.search', ''));
        $type = trim((string) $request->input('filter.type', ''));

        $entries = ListPage::of(
            ConsoleActionLog::query()
                // L'auteur ou l'organisation : ce que l'on cherche dans un journal.
                ->when($search !== '', fn (Builder $query) => UnaccentedSearch::apply($query, ['actor_name', 'organisation'], $search))
                ->when($type !== '' && $type !== 'all', fn (Builder $query) => $query->where('type', $type))
                ->latest('created_at')
                ->latest('id'),
            $request,
        );

        return Inertia::render('console/audit', [
            'isSample' => false,
            'entries' => $entries->getCollection()
                ->map(fn (ConsoleActionLog $entry) => [
                    'id' => $entry->id,
                    'at' => $entry->created_at->toISOString(),
                    'actor' => $entry->actor_name,
                    'type' => $entry->type,
                    'organisation' => $entry->organisation,
                    'ip' => $entry->ip,
                ])
                ->values()
                ->all(),
            'meta' => ListPage::meta($entries),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'type' => $type !== '' ? $type : 'all',
            ],
        ]);
    }
}
