<?php

namespace App\Support\Health\Checks;

use App\Support\Health\ScheduledTasks;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Une tache planifiee en echec ou en retard (README ecran 31). Le controle du paquet dit seulement
 * que le planificateur tourne ; celui-ci dit qu'une tache precise, une purge ou un rappel, ne fait
 * plus son travail alors que les autres passent.
 */
class ScheduledTasksCheck extends Check
{
    public function run(): Result
    {
        $counts = ScheduledTasks::counts();

        $result = Result::make()
            ->meta($counts)
            ->shortSummary("{$counts['failed']} / {$counts['late']}");

        if ($counts['failed'] > 0) {
            return $result->failed("{$counts['failed']} scheduled task(s) failed on their last run.");
        }

        if ($counts['late'] > 0) {
            return $result->failed("{$counts['late']} scheduled task(s) missed their last due time.");
        }

        return $result->ok();
    }
}
