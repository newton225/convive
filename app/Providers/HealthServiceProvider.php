<?php

namespace App\Providers;

use App\Support\Health\Checks\FailedJobsCheck;
use App\Support\Health\Checks\ScheduledTasksCheck;
use App\Support\Health\ScheduledTaskRecorder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

/**
 * La surveillance de l'application (`spatie/laravel-health`, README ecran 31) : les controles joues
 * chaque minute par le planificateur, lus par l'ecran de sante technique, et dont un echec previent
 * par courriel (`config/health.php`).
 *
 * Les noms des controles sont ceux que l'ecran sait traduire (`console.health.checks`).
 */
class HealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::subscribe(ScheduledTaskRecorder::class);

        Health::checks([
            // Le planificateur lui-meme : s'il s'arrete, plus rien ne tourne, ni purge ni rappel.
            ScheduleCheck::new()->name('schedule')->heartbeatMaxAgeInMinutes(2),
            ScheduledTasksCheck::new()->name('scheduled_tasks'),
            // Un travail temoin part chaque minute : s'il n'est pas traite, aucun envoi ne l'est.
            QueueCheck::new()->name('queue')->failWhenHealthJobTakesLongerThanMinutes(5),
            FailedJobsCheck::new()->name('failed_jobs'),
            DatabaseCheck::new()->name('database')->connectionName('central'),
            RedisCheck::new()->name('redis')->if(fn () => in_array('redis', [config('queue.default'), config('cache.default')], true)),
            // Le controle lit la commande `df`, absente d'un poste Windows de developpement.
            UsedDiskSpaceCheck::new()->name('disk')
                ->warnWhenUsedSpaceIsAbovePercentage(80)
                ->failWhenUsedSpaceIsAbovePercentage(90)
                ->unless(PHP_OS_FAMILY === 'Windows'),
        ]);
    }
}
