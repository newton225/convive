<?php

namespace App\Support\Health;

use App\Models\ScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Tient le releve des taches planifiees (README ecran 31) a partir des evenements que le
 * planificateur de Laravel emet lui-meme : debut, fin, echec, tache ecartee.
 *
 * Chaque ecriture est protegee : le planificateur emet « debut » dans le meme bloc que la tache, et
 * une erreur ici empecherait la tache de tourner. Un releve manque vaut mieux qu'une purge ou un
 * rappel qui ne part pas.
 */
class ScheduledTaskRecorder
{
    /**
     * Une tache que le planificateur n'a plus vue depuis ce delai a ete retiree du code.
     */
    private const ForgottenAfterDays = 2;

    /**
     * Le message d'un echec est garde court : le detail complet est au journal du serveur.
     */
    private const FailureLength = 500;

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ScheduledTaskStarting::class, fn (ScheduledTaskStarting $event) => $this->record($event->task, [
            'last_started_at' => now(),
        ]));

        $events->listen(ScheduledTaskFinished::class, fn (ScheduledTaskFinished $event) => $this->record($event->task, [
            'last_finished_at' => now(),
            'last_runtime_ms' => (int) round($event->runtime * 1000),
        ]));

        $events->listen(ScheduledTaskSkipped::class, fn (ScheduledTaskSkipped $event) => $this->record($event->task, [
            'last_skipped_at' => now(),
        ]));

        $events->listen(ScheduledTaskFailed::class, fn (ScheduledTaskFailed $event) => $this->record($event->task, [
            'last_failed_at' => now(),
            'last_failure' => Str::limit($event->exception->getMessage(), self::FailureLength),
        ]));
    }

    /**
     * Get the name a task is recorded under : sa description, ou a defaut la commande lancee.
     */
    public static function nameOf(ScheduledEvent $task): string
    {
        return Str::limit((string) ($task->description ?: $task->getSummaryForDisplay()), 250, '');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function record(ScheduledEvent $task, array $attributes): void
    {
        rescue(function () use ($task, $attributes) {
            $this->syncSchedule();

            ScheduledTaskRun::updateOrCreate(
                ['name' => self::nameOf($task)],
                ['expression' => $task->expression, 'seen_at' => now(), ...$attributes],
            );
        });
    }

    /**
     * Note every task of the schedule, including the ones not due yet, and forget the ones removed
     * from the code. Sans cela, une tache quotidienne n'apparaitrait qu'apres sa premiere nuit, et
     * une tache supprimee resterait « en retard » pour toujours.
     *
     * Le planificateur passe chaque minute : le releve complet n'est refait que toutes les dix.
     */
    private function syncSchedule(): void
    {
        if (! Cache::add('health:scheduled-tasks:synced', true, now()->addMinutes(10))) {
            return;
        }

        foreach (app(Schedule::class)->events() as $task) {
            ScheduledTaskRun::updateOrCreate(
                ['name' => self::nameOf($task)],
                ['expression' => $task->expression, 'seen_at' => now()],
            );
        }

        ScheduledTaskRun::where('seen_at', '<', now()->subDays(self::ForgottenAfterDays))->delete();
    }
}
