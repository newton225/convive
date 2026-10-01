<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\Artisan;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;

/**
 * Les controles de sante vus par l'ecran de la console (README ecran 31). Ils sont rejoues a
 * l'affichage, comme le fait la page du paquet avec `?fresh` : si le planificateur est arrete, un
 * resultat garde en cache serait justement celui d'avant la panne.
 *
 * Rejoues sans notification : c'est la tache planifiee qui previent, pas l'ouverture d'un ecran.
 */
class HealthChecks
{
    /**
     * @return array<int, array{name: string, status: string, summary: string}>
     */
    public static function fresh(): array
    {
        Artisan::call(RunHealthChecksCommand::class, ['--no-notification' => true]);

        $results = app(ResultStore::class)->latestResults();

        if ($results === null) {
            return [];
        }

        return $results->storedCheckResults
            ->map(fn (StoredCheckResult $result) => [
                'name' => $result->name,
                // `ok`, `warning`, `failed`, `crashed` ou `skipped`.
                'status' => $result->status,
                'summary' => $result->shortSummary,
            ])
            ->values()
            ->all();
    }
}
