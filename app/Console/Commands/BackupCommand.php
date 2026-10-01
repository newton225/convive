<?php

namespace App\Console\Commands;

use App\Support\Backup\DatabaseSnapshots;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * La sauvegarde de Convive : une copie de chaque base (centrale et organisations), puis l'archive
 * de `spatie/laravel-backup` qui les emporte avec les fichiers de marque et les preuves de paiement
 * (`config/backup.php`). C'est cette commande qui est planifiee, pas `backup:run` : lancee seule,
 * celle-ci archiverait les fichiers sans aucune base.
 */
#[Signature('convive:backup')]
#[Description('Sauvegarde les bases de toutes les organisations et leurs fichiers')]
class BackupCommand extends Command
{
    public function handle(): int
    {
        try {
            $count = DatabaseSnapshots::take();
            $this->components->info("Bases copiees : {$count}.");

            return $this->call('backup:run', ['--only-files' => true]);
        } finally {
            DatabaseSnapshots::clear();
        }
    }
}
