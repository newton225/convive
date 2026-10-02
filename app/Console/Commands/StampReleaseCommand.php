<?php

namespace App\Console\Commands;

use App\Support\Release;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * A jouer a chaque livraison, apres la mise a jour du code : note la date et l'identifiant de la
 * modification en service. Le back-office les affiche a cote du numero de version, pour qu'un
 * membre qui signale un probleme dise exactement ce qu'il avait sous les yeux.
 */
#[Signature('convive:release {--commit= : Identifiant de la modification livree, quand git n\'est pas disponible sur le serveur}')]
#[Description('Estampille la livraison en service : sa date et l\'identifiant de sa derniere modification')]
class StampReleaseCommand extends Command
{
    public function handle(): int
    {
        $release = Release::stamp($this->commit());

        $this->components->info(sprintf(
            'Livraison estampillee : version %s, %s%s.',
            config('convive.version'),
            $release['released_at'],
            $release['commit'] !== null ? ', modification '.$release['commit'] : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Get the short identifier of the delivered change : celui donne en option, sinon celui que git
     * connait. Sans l'un ni l'autre, la livraison n'est datee que par son jour.
     */
    private function commit(): ?string
    {
        $given = $this->option('commit');

        if (is_string($given) && $given !== '') {
            return substr($given, 0, 12);
        }

        $git = Process::path(base_path())->run('git rev-parse --short HEAD');
        $commit = trim($git->output());

        return $git->successful() && $commit !== '' ? $commit : null;
    }
}
