<?php

namespace Tests\Feature\Console;

use App\Enums\ConsoleProfile;
use App\Models\ConsoleActionLog;
use App\Models\ConsoleOperator;
use App\Models\ScheduledTaskRun;
use App\Models\User;
use App\Support\Health\Checks\FailedJobsCheck;
use App\Support\Health\Checks\ScheduledTasksCheck;
use App\Support\Health\QueueOverview;
use App\Support\Health\ScheduledTasks;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * Le suivi des taches planifiees et des files (README ecran 31) : le planificateur releve ce qu'il
 * fait de chaque tache, l'ecran dit laquelle est en retard ou en echec, et un envoi en echec se
 * relance ou s'ecarte depuis la console.
 */
class HealthMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $founder;

    protected function setUp(): void
    {
        parent::setUp();

        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $this->founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);
    }

    private function task(string $name = 'Tache de test'): ScheduledEvent
    {
        return app(Schedule::class)->call(fn () => null)->everyMinute()->description($name);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recorded(string $expression, array $attributes = []): ScheduledTaskRun
    {
        return ScheduledTaskRun::create([
            'name' => 'Tache de test',
            'expression' => $expression,
            'seen_at' => now(),
            ...$attributes,
        ]);
    }

    private function failedJob(): string
    {
        return (string) app('queue.failer')->log('sync', 'default', '{"displayName":"App\\\\Jobs\\\\EnvoiDeTest"}', new RuntimeException('Adresse refusee.'));
    }

    public function test_le_planificateur_releve_le_debut_et_la_fin_d_une_tache(): void
    {
        $task = $this->task();

        event(new ScheduledTaskStarting($task));
        event(new ScheduledTaskFinished($task, 1.5));

        $run = ScheduledTaskRun::where('name', 'Tache de test')->firstOrFail();

        $this->assertNotNull($run->last_started_at);
        $this->assertSame(1500, $run->last_runtime_ms);
        $this->assertSame('* * * * *', $run->expression);
        $this->assertSame('ok', $run->state());
    }

    public function test_une_tache_qui_echoue_est_relevee_avec_son_message(): void
    {
        $task = $this->task();

        event(new ScheduledTaskStarting($task));
        event(new ScheduledTaskFailed($task, new RuntimeException('Base illisible.')));

        $run = ScheduledTaskRun::where('name', 'Tache de test')->firstOrFail();

        $this->assertSame('failed', $run->state());
        $this->assertSame('Base illisible.', $run->last_failure);
        $this->assertSame('failed', ScheduledTasksCheck::new()->run()->status->value);
    }

    public function test_une_execution_reussie_efface_l_echec_precedent(): void
    {
        $run = $this->recorded('* * * * *', ['last_started_at' => now()->subMinute(), 'last_failed_at' => now()->subMinute()]);
        $this->assertSame('failed', $run->state());

        $run->update(['last_started_at' => now()]);

        $this->assertSame('ok', $run->state());
    }

    public function test_une_tache_de_chaque_minute_arretee_est_en_retard(): void
    {
        $run = $this->recorded('* * * * *', ['last_started_at' => now()->subMinutes(20)]);

        $this->assertSame('late', $run->state());
        $this->assertSame('failed', ScheduledTasksCheck::new()->run()->status->value);
    }

    public function test_un_passage_manque_reste_dans_la_marge(): void
    {
        $run = $this->recorded('* * * * *', ['last_started_at' => now()->subMinutes(2)]);

        $this->assertSame('ok', $run->state());
    }

    public function test_une_tache_ecartee_par_le_planificateur_n_est_pas_en_retard(): void
    {
        $run = $this->recorded('* * * * *', ['last_started_at' => now()->subMinutes(20), 'last_skipped_at' => now()]);

        $this->assertSame('ok', $run->state());
    }

    public function test_une_tache_quotidienne_n_est_en_retard_qu_apres_avoir_manque_son_heure(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $onTime = $this->recorded('30 2 * * *', ['last_started_at' => now()->setTime(2, 30, 5)]);
        $this->assertSame('ok', $onTime->state());

        $onTime->update(['last_started_at' => now()->subDay()->setTime(2, 30, 5)]);
        $this->assertSame('late', $onTime->state());
    }

    public function test_une_tache_jamais_lancee_attend_sa_premiere_heure(): void
    {
        $this->travelTo(now()->setTime(12, 0));

        $run = $this->recorded('30 2 * * *');
        $this->assertSame('waiting', $run->state());

        $this->travel(1)->days();
        $this->assertSame('late', $run->fresh()->state());
    }

    public function test_l_ecran_montre_les_controles_les_taches_et_la_file(): void
    {
        $this->recorded('* * * * *', ['last_started_at' => now()]);
        $this->failedJob();

        $this->actingAs($this->founder)
            ->get(route('console.health'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isSample', false)
                ->has('checks')
                ->has('tasks', 1)
                ->where('tasks.0.state', 'ok')
                ->where('queue.failedCount', 1)
                ->where('queue.failed.0.job', 'EnvoiDeTest'),
            );
    }

    public function test_un_envoi_en_echec_fait_echouer_le_controle(): void
    {
        $this->assertSame('ok', FailedJobsCheck::new()->run()->status->value);

        $this->failedJob();

        $this->assertSame('failed', FailedJobsCheck::new()->run()->status->value);
    }

    public function test_un_fondateur_ecarte_un_envoi_en_echec_et_le_geste_est_journalise(): void
    {
        $id = $this->failedJob();

        $this->actingAs($this->founder)
            ->delete(route('console.health.failed-jobs.destroy', $id))
            ->assertRedirect(route('console.health'));

        $this->assertSame(0, QueueOverview::failedCount());
        $this->assertTrue(ConsoleActionLog::where('type', 'failed_job_forgotten')->where('actor_id', $this->founder->id)->exists());
    }

    public function test_un_fondateur_relance_un_envoi_en_echec(): void
    {
        $id = $this->failedJob();

        $this->actingAs($this->founder)
            ->post(route('console.health.failed-jobs.retry', $id))
            ->assertRedirect(route('console.health'));

        $this->assertTrue(ConsoleActionLog::where('type', 'failed_job_retried')->exists());
    }

    public function test_un_envoi_inconnu_recoit_404(): void
    {
        $this->actingAs($this->founder)
            ->post(route('console.health.failed-jobs.retry', 'inconnu'))
            ->assertNotFound();

        $this->actingAs($this->founder)
            ->delete(route('console.health.failed-jobs.destroy', 'inconnu'))
            ->assertNotFound();
    }

    public function test_seul_un_profil_qui_ouvre_la_sante_technique_agit_sur_un_envoi_en_echec(): void
    {
        $id = $this->failedJob();

        ConsoleOperator::create(['email' => 'support@convive.test', 'profile' => ConsoleProfile::Support]);
        $support = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test']);

        $this->actingAs($support)
            ->delete(route('console.health.failed-jobs.destroy', $id))
            ->assertForbidden();

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->delete(route('console.health.failed-jobs.destroy', $id))
            ->assertNotFound();

        $this->assertSame(1, QueueOverview::failedCount());
    }

    public function test_les_taches_en_echec_arrivent_en_tete_du_releve(): void
    {
        ScheduledTaskRun::create(['name' => 'A l heure', 'expression' => '* * * * *', 'seen_at' => now(), 'last_started_at' => now()]);
        ScheduledTaskRun::create(['name' => 'Z en echec', 'expression' => '* * * * *', 'seen_at' => now(), 'last_started_at' => now(), 'last_failed_at' => now(), 'last_failure' => 'Erreur.']);

        $this->assertSame('Z en echec', ScheduledTasks::overview()[0]['name']);
    }
}
