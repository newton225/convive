<?php

namespace App\Support;

use App\Models\RegistrationReferenceCounter;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reference de dossier lisible, « SP-2026-0008 » (prototype Convive.dc.html) : initiales de
 * l'organisation, annee, numero sur quatre chiffres.
 *
 * Le numero se suit, et SECURITY.md H3 le permet parce qu'il est decouple de l'identifiant
 * technique et ne sert qu'a l'affichage : aucune route ne retrouve un dossier par sa reference, un
 * dossier public reste adresse par son jeton de reprise.
 */
final class RegistrationReference
{
    /**
     * Mots ignores pour les initiales : liaisons et formes juridiques, qui ne distinguent pas une
     * organisation d'une autre (« Association des Soldats du Palais » donne « SP »).
     */
    private const IgnoredWords = [
        'de', 'des', 'du', 'la', 'le', 'les', 'l', 'd', 'et', 'en', 'au', 'aux',
        'of', 'the', 'and',
        'association', 'asso', 'fondation', 'ong', 'sarl', 'sas', 'sa', 'ets',
    ];

    private const Fallback = 'CV';

    /**
     * Get the next reference for the current organisation, numbering within the current year.
     *
     * Verrou applicatif plus transaction : SQLite ignore `lockForUpdate()` (CLAUDE.md, « Base de
     * donnees »), et deux inscriptions simultanees ne doivent jamais recevoir le meme numero.
     * L'unicite de `registrations.reference` reste le filet en dernier ressort.
     */
    public static function next(): string
    {
        $tenant = Tenant::current();
        $prefix = self::initials($tenant->branding->display_name ?? $tenant->name ?? '');
        $year = (int) now()->format('Y');

        $number = Cache::lock('registration-reference', 10)->block(5, fn () => DB::transaction(function () use ($year) {
            $counter = RegistrationReferenceCounter::firstOrCreate(['year' => $year], ['last_number' => 0]);
            $counter->increment('last_number');

            return $counter->last_number;
        }));

        return sprintf('%s-%d-%04d', $prefix, $year, $number);
    }

    /**
     * Get up to three capital initials of an organisation name, or the fallback when none.
     */
    public static function initials(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/[^A-Za-z0-9]+/', Str::ascii($name)) ?: [],
            fn (string $word) => $word !== '' && ! in_array(strtolower($word), self::IgnoredWords, true),
        ));

        if ($words === []) {
            return self::Fallback;
        }

        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 2));
        }

        return strtoupper(implode('', array_map(fn (string $word) => $word[0], array_slice($words, 0, 3))));
    }
}
