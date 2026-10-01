<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Ce que le planificateur a fait d'une tache (README ecran 31) : sa derniere execution, son dernier
 * echec, et sa frequence. « En retard » et « en echec » ne sont pas stockes : ils se lisent sur ces
 * dates a chaque affichage, pour qu'un planificateur arrete se voie sans que rien n'ait a tourner.
 *
 * @property int $id
 * @property string $name
 * @property string $expression
 * @property CarbonInterface $seen_at
 * @property CarbonInterface|null $last_started_at
 * @property CarbonInterface|null $last_finished_at
 * @property int|null $last_runtime_ms
 * @property CarbonInterface|null $last_skipped_at
 * @property CarbonInterface|null $last_failed_at
 * @property string|null $last_failure
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'expression', 'seen_at', 'last_started_at', 'last_finished_at', 'last_runtime_ms', 'last_skipped_at', 'last_failed_at', 'last_failure'])]
class ScheduledTaskRun extends Model
{
    use CentralConnection;

    /**
     * Marge laissee a une tache apres son heure avant de la dire en retard : le planificateur
     * passe une fois par minute, et une tache longue peut retarder la suivante.
     */
    public const GraceMinutes = 5;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the last due time the task should already have honoured : la derniere heure prevue qui
     * a depasse la marge. Mesuree depuis « maintenant moins la marge », et non depuis maintenant :
     * une tache de chaque minute a toujours une heure prevue toute recente, encore dans sa marge,
     * et ne serait jamais dite en retard.
     */
    public function previousDueAt(): CarbonInterface
    {
        return CarbonImmutable::instance(
            (new CronExpression($this->expression))->getPreviousRunDate(now()->subMinutes(self::GraceMinutes), 0, true),
        );
    }

    /**
     * Get the last moment the scheduler dealt with the task : lancee, ou ecartee volontairement
     * (une execution precedente encore en cours, par exemple).
     */
    public function lastHandledAt(): ?CarbonInterface
    {
        return collect([$this->last_started_at, $this->last_skipped_at])->filter()->max();
    }

    /**
     * Determine whether the task missed its last due time. Une tache jamais lancee se juge depuis
     * le moment ou elle a ete relevee pour la premiere fois.
     */
    public function isLate(): bool
    {
        return ($this->lastHandledAt() ?? $this->created_at ?? now())->lt($this->previousDueAt());
    }

    /**
     * Determine whether the most recent run of the task failed.
     */
    public function hasFailed(): bool
    {
        return $this->last_failed_at !== null
            && ($this->last_started_at === null || $this->last_failed_at->gte($this->last_started_at));
    }

    /**
     * Get the state the health screen shows : `failed`, `late`, `waiting` ou `ok`.
     */
    public function state(): string
    {
        return match (true) {
            $this->hasFailed() => 'failed',
            $this->isLate() => 'late',
            $this->last_started_at === null => 'waiting',
            default => 'ok',
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
            'last_started_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'last_skipped_at' => 'datetime',
            'last_failed_at' => 'datetime',
        ];
    }
}
