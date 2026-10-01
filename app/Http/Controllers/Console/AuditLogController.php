<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\ConsoleActionLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le journal central (README ecran 33) : les actions de la console, les plus recentes d'abord.
 * Ecriture seule, tenu par `ConsoleJournal`.
 */
class AuditLogController extends Controller
{
    /**
     * Nombre d'entrees relues a l'ecran. Au-dela, la recherche passera cote serveur.
     */
    private const EntriesShown = 300;

    public function __invoke(): Response
    {
        return Inertia::render('console/audit', [
            'isSample' => false,
            'entries' => ConsoleActionLog::query()
                ->latest('created_at')
                ->latest('id')
                ->limit(self::EntriesShown)
                ->get()
                ->map(fn (ConsoleActionLog $entry) => [
                    'id' => $entry->id,
                    'at' => $entry->created_at->toISOString(),
                    'actor' => $entry->actor_name,
                    'type' => $entry->type,
                    'organisation' => $entry->organisation,
                    'ip' => $entry->ip,
                ])
                ->all(),
        ]);
    }
}
