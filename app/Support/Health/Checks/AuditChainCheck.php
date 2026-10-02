<?php

namespace App\Support\Health\Checks;

use App\Support\Console\SecurityJournal;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Un journal d'audit dont la chaine d'empreintes est rompue (SECURITY.md M6) : une entree a ete
 * modifiee ou supprimee en dehors de la purge tracee. Lu sur le resultat de la verification
 * quotidienne, pour qu'une rupture previenne par courriel au lieu de rester au journal du serveur.
 */
class AuditChainCheck extends Check
{
    public function run(): Result
    {
        $broken = SecurityJournal::brokenChains();

        $result = Result::make()->meta(['broken' => $broken])->shortSummary((string) $broken);

        return $broken > 0
            ? $result->failed("{$broken} audit log(s) have a broken hash chain.")
            : $result->ok();
    }
}
