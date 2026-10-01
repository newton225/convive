import type { ColumnDef } from '@tanstack/react-table';
import { ConsoleTable } from '@/components/console/console-table';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { formatRelative } from '@/lib/format-date';
import type { ConsoleScheduledTask } from '@/types';

type Props = {
    tasks: ConsoleScheduledTask[];
};

/**
 * Le releve des taches planifiees (README ecran 31) : ce que fait chaque tache, sa frequence, sa
 * derniere execution et son etat. Celles en echec ou en retard arrivent en tete, avec le message
 * de l'erreur.
 */
export function ScheduledTasksTable({ tasks }: Props) {
    const { t, locale } = useTranslation();

    const frequencyLabel = (task: ConsoleScheduledTask) => {
        const { kind, minutes, at, expression } = task.frequency;

        if (kind === 'minutes' && minutes !== null) {
            return t('console.health.frequency.minutes', { count: minutes });
        }

        if (kind === 'hourly') {
            return t('console.health.frequency.hourly');
        }

        if (kind === 'daily' && at !== null) {
            return t('console.health.frequency.daily', { at });
        }

        return expression;
    };

    const columns: ColumnDef<ConsoleScheduledTask>[] = [
        {
            header: t('console.health.task_columns.task'),
            cell: ({ row }) => (
                <span>
                    {row.original.label}
                    {row.original.failure ? (
                        <span className="text-muted-foreground block text-xs">
                            {row.original.failure}
                        </span>
                    ) : null}
                </span>
            ),
        },
        {
            header: t('console.health.task_columns.frequency'),
            cell: ({ row }) => frequencyLabel(row.original),
        },
        {
            header: t('console.health.task_columns.last_run'),
            cell: ({ row }) =>
                row.original.lastRunAt
                    ? formatRelative(row.original.lastRunAt, locale)
                    : t('console.health.never_run'),
        },
        {
            header: t('console.health.task_columns.state'),
            cell: ({ row }) => {
                const { state } = row.original;

                return (
                    <Badge
                        variant={
                            state === 'failed' || state === 'late'
                                ? 'destructive'
                                : state === 'ok'
                                  ? 'secondary'
                                  : 'outline'
                        }
                    >
                        {t(`console.health.task_states.${state}`)}
                    </Badge>
                );
            },
        },
    ];

    return (
        <section className="space-y-3" data-test="console-scheduled-tasks">
            <h3 className="font-medium">{t('console.health.tasks')}</h3>
            <ConsoleTable
                columns={columns}
                data={tasks}
                emptyState={
                    <p className="text-muted-foreground text-sm">
                        {t('console.health.tasks_empty')}
                    </p>
                }
            />
        </section>
    );
}
