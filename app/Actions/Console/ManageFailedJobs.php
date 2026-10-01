<?php

namespace App\Actions\Console;

use App\Models\User;
use App\Support\Console\ConsoleJournal;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;

/**
 * Ce que l'editeur fait d'un travail de file en echec (README ecran 31) : le relancer, une fois la
 * cause corrigee, ou l'ecarter quand il n'a plus lieu d'etre. Les deux gestes passent par le
 * mecanisme de Laravel (`queue:retry`, `queue.failer`) et s'ecrivent au journal central.
 */
class ManageFailedJobs
{
    /**
     * Put the failed job back on its queue. Returns false when it no longer exists.
     */
    public function retry(string $id, User $actor): bool
    {
        if ($this->failer()->find($id) === null) {
            return false;
        }

        Artisan::call('queue:retry', ['id' => [$id]]);

        ConsoleJournal::record('failed_job_retried', $actor, null, ['job' => $id]);

        return true;
    }

    /**
     * Drop the failed job for good : il ne sera jamais rejoue.
     */
    public function forget(string $id, User $actor): bool
    {
        if (! $this->failer()->forget($id)) {
            return false;
        }

        ConsoleJournal::record('failed_job_forgotten', $actor, null, ['job' => $id]);

        return true;
    }

    private function failer(): FailedJobProviderInterface
    {
        return app('queue.failer');
    }
}
