<?php

namespace App\Support\Health;

use App\Models\ScheduledTaskRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Le releve des taches planifiees tel que l'ecran de sante technique le montre (README ecran 31) :
 * ce que fait chaque tache, a quelle frequence, quand elle a tourne, et dans quel etat elle est.
 */
class ScheduledTasks
{
    /**
     * @return array<int, array{name: string, label: string, frequency: array{kind: string, minutes: int|null, at: string|null, expression: string}, lastRunAt: string|null, runtimeMs: int|null, state: string, failure: string|null}>
     */
    public static function overview(): array
    {
        return self::runs()
            ->map(fn (ScheduledTaskRun $run) => [
                'name' => $run->name,
                'label' => self::label($run->name),
                'frequency' => self::frequency($run->expression),
                'lastRunAt' => $run->last_started_at?->toISOString(),
                'runtimeMs' => $run->last_runtime_ms,
                'state' => $run->state(),
                'failure' => $run->hasFailed() ? $run->last_failure : null,
            ])
            // Ce qui demande une action d'abord.
            ->sortBy(fn (array $task) => array_search($task['state'], ['failed', 'late', 'waiting', 'ok'], true))
            ->values()
            ->all();
    }

    /**
     * Count the tasks by state, for the health check.
     *
     * @return array{failed: int, late: int, total: int}
     */
    public static function counts(): array
    {
        $states = self::runs()->map(fn (ScheduledTaskRun $run) => $run->state());

        return [
            'failed' => $states->filter(fn (string $state) => $state === 'failed')->count(),
            'late' => $states->filter(fn (string $state) => $state === 'late')->count(),
            'total' => $states->count(),
        ];
    }

    /**
     * @return Collection<int, ScheduledTaskRun>
     */
    private static function runs(): Collection
    {
        return ScheduledTaskRun::query()->orderBy('name')->get();
    }

    /**
     * Les taches portent une description en anglais dans `routes/console.php`, lue par
     * `schedule:list`. L'ecran en donne une traduction ; une tache sans traduction garde sa
     * description, pour qu'elle se voie plutot que de disparaitre.
     */
    private static function label(string $name): string
    {
        $key = 'console.health.task_labels.'.Str::slug($name, '_');

        return Lang::has($key) ? __($key) : $name;
    }

    /**
     * Read the usual frequencies of the schedule from the cron expression ; toute autre garde son
     * expression, que l'ecran affiche telle quelle.
     *
     * @return array{kind: string, minutes: int|null, at: string|null, expression: string}
     */
    private static function frequency(string $expression): array
    {
        $frequency = ['kind' => 'other', 'minutes' => null, 'at' => null, 'expression' => $expression];

        if ($expression === '* * * * *') {
            return [...$frequency, 'kind' => 'minutes', 'minutes' => 1];
        }

        if (preg_match('/^\*\/(\d+) \* \* \* \*$/', $expression, $matches) === 1) {
            return [...$frequency, 'kind' => 'minutes', 'minutes' => (int) $matches[1]];
        }

        if (preg_match('/^(\d+) \* \* \* \*$/', $expression) === 1) {
            return [...$frequency, 'kind' => 'hourly'];
        }

        if (preg_match('/^(\d+) (\d+) \* \* \*$/', $expression, $matches) === 1) {
            return [...$frequency, 'kind' => 'daily', 'at' => sprintf('%02d:%02d', $matches[2], $matches[1])];
        }

        return $frequency;
    }
}
