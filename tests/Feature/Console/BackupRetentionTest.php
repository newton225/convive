<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La conservation des sauvegardes (CLAUDE.md, « Sauvegardes ») : une organisation effacee ne survit
 * pas plus d'un an dans les archives. C'est un engagement pris envers les organisations, annonce
 * dans la confirmation de suppression : la configuration ne doit pas pouvoir le depasser sans
 * qu'un test le dise.
 */
class BackupRetentionTest extends TestCase
{
    public function test_aucune_archive_n_est_gardee_plus_d_un_an(): void
    {
        $strategy = config('backup.cleanup.default_strategy');

        $oldestKept = Carbon::now()
            ->subDays($strategy['keep_all_backups_for_days'])
            ->subDays($strategy['keep_daily_backups_for_days'])
            ->subWeeks($strategy['keep_weekly_backups_for_weeks'])
            ->subMonths($strategy['keep_monthly_backups_for_months'])
            ->subYears($strategy['keep_yearly_backups_for_years']);

        $this->assertTrue(
            $oldestKept->greaterThanOrEqualTo(Carbon::now()->subYear()),
            'Les durees de conservation additionnees depassent un an.',
        );
    }

    public function test_aucune_archive_annuelle_n_est_gardee(): void
    {
        $this->assertSame(0, config('backup.cleanup.default_strategy.keep_yearly_backups_for_years'));
    }
}
