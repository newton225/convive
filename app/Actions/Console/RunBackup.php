<?php

namespace App\Actions\Console;

use App\Models\User;
use App\Support\Console\ConsoleJournal;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * « Sauvegarder maintenant » (README ecran 31) : la meme commande que la tache quotidienne, lancee
 * depuis la console, avant une operation risquee par exemple.
 *
 * Jouee dans la requete, pour que l'ecran dise si elle a abouti. A passer en file le jour ou le
 * volume des fichiers la rend plus longue qu'une requete.
 */
class RunBackup
{
    /**
     * @throws RuntimeException when the backup did not complete
     * @throws LockTimeoutException when another backup is already running
     */
    public function handle(User $actor): void
    {
        // Deux sauvegardes en meme temps se disputeraient les memes copies de bases.
        Cache::lock('console:backup', 600)->block(2, function () use ($actor) {
            $exitCode = Artisan::call('convive:backup');

            ConsoleJournal::record('backup_run', $actor, null, ['exit_code' => $exitCode]);

            if ($exitCode !== 0) {
                throw new RuntimeException(Artisan::output());
            }
        });
    }
}
