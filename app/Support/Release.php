<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/**
 * La livraison en service : la date ou le code a ete mis en ligne et l'identifiant de sa derniere
 * modification. Elle complete le numero de version choisi par le proprietaire du projet
 * (`config('convive.version')`, decision du 2026-10-02 : « les deux »), qui ne dit pas, a lui seul,
 * ce qui tourne reellement.
 *
 * L'estampille est un fichier pose par `php artisan convive:release` a chaque livraison, hors du
 * depot : elle decrit ce serveur-ci, pas le code.
 */
class Release
{
    /**
     * Write the stamp of the delivery being put in service.
     *
     * @return array{released_at: string, commit: string|null}
     */
    public static function stamp(?string $commit): array
    {
        $release = ['released_at' => now()->toISOString(), 'commit' => $commit];

        File::ensureDirectoryExists(dirname(self::path()));
        File::put(self::path(), (string) json_encode($release));

        return $release;
    }

    /**
     * Get the delivery in service, or null when none was ever stamped (a development machine).
     *
     * @return array{releasedAt: string, commit: string|null}|null
     */
    public static function current(): ?array
    {
        if (! File::exists(self::path())) {
            return null;
        }

        $release = json_decode(File::get(self::path()), true);

        if (! is_array($release) || ! is_string($release['released_at'] ?? null)) {
            return null;
        }

        return [
            'releasedAt' => CarbonImmutable::parse($release['released_at'])->toISOString(),
            'commit' => is_string($release['commit'] ?? null) ? $release['commit'] : null,
        ];
    }

    private static function path(): string
    {
        return (string) config('convive.release_path');
    }
}
