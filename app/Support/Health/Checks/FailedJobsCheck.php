<?php

namespace App\Support\Health\Checks;

use App\Support\Health\QueueOverview;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Des travaux de file en echec definitif (README ecran 31) : un courriel, un message WhatsApp ou une
 * alerte qui n'est pas parti apres toutes ses tentatives. Rien ne le signalait : l'invite ne recoit
 * pas sa carte, et personne ne le sait.
 */
class FailedJobsCheck extends Check
{
    public function run(): Result
    {
        $count = QueueOverview::failedCount();

        $result = Result::make()
            ->meta(['count' => $count])
            ->shortSummary((string) $count);

        return $count > 0
            ? $result->failed("{$count} queued job(s) failed for good.")
            : $result->ok();
    }
}
