import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { CircleAlert, CircleCheck, DatabaseZap } from 'lucide-react';
import { ConsoleTable } from '@/components/console/console-table';
import { PendingActionButton } from '@/components/console/pending-action-button';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime, formatRelative } from '@/lib/format-date';
import { health } from '@/routes/console';
import { show } from '@/routes/console/organisations';
import type {
    ConsoleBackup,
    ConsoleDatabaseIssue,
    ConsoleQueue,
    ConsoleScheduledTask,
    Translations,
} from '@/types';

type Props = {
    isSample: boolean;
    databases: ConsoleDatabaseIssue[];
    tasks: ConsoleScheduledTask[];
    queues: ConsoleQueue[];
    backup: ConsoleBackup;
};

/**
 * README ecran 31 : la sante technique. Bases d'organisation absentes ou en retard de migrations
 * (CLAUDE.md, « Multi-locataire » : un locataire sans ses migrations est un locataire casse),
 * taches planifiees en retard, files bloquees, sauvegardes.
 */
export default function Health({
    isSample,
    databases,
    tasks,
    queues,
    backup,
}: Props) {
    const { t, locale } = useTranslation();

    const taskColumns: ColumnDef<ConsoleScheduledTask>[] = [
        {
            header: t('console.health.task_columns.task'),
            cell: ({ row }) =>
                t(`console.health.task_labels.${row.original.key}`),
        },
        {
            header: t('console.health.task_columns.frequency'),
            cell: ({ row }) =>
                t('console.health.every_minutes', {
                    minutes: String(row.original.everyMinutes),
                }),
        },
        {
            header: t('console.health.task_columns.last_run'),
            cell: ({ row }) => formatRelative(row.original.lastRunAt, locale),
        },
        {
            header: t('console.health.task_columns.state'),
            cell: ({ row }) =>
                row.original.late ? (
                    <Badge variant="destructive">
                        {t('console.health.late')}
                    </Badge>
                ) : (
                    <Badge variant="secondary">
                        {t('console.health.on_time')}
                    </Badge>
                ),
        },
    ];

    const queueColumns: ColumnDef<ConsoleQueue>[] = [
        {
            header: t('console.health.queue_columns.name'),
            cell: ({ row }) => (
                <span className="font-mono text-xs">{row.original.name}</span>
            ),
        },
        {
            header: t('console.health.queue_columns.pending'),
            accessorKey: 'pending',
        },
        {
            header: t('console.health.queue_columns.failed'),
            cell: ({ row }) => (
                <span
                    className={
                        row.original.failed > 0 ? 'font-semibold' : undefined
                    }
                >
                    {row.original.failed}
                </span>
            ),
        },
        {
            header: t('console.health.queue_columns.oldest'),
            cell: ({ row }) =>
                row.original.oldestAt
                    ? formatRelative(row.original.oldestAt, locale)
                    : '',
        },
    ];

    return (
        <>
            <Head title={t('console.health.title')} />

            <div className="flex flex-col space-y-6">
                {isSample && <SampleBanner />}

                <Heading
                    variant="small"
                    title={t('console.health.title')}
                    description={t('console.health.description')}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{t('console.health.databases')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {databases.length === 0 ? (
                            <p className="flex items-center gap-2 text-sm">
                                <CircleCheck className="size-4" />
                                {t('console.health.databases_ok')}
                            </p>
                        ) : (
                            <ul className="divide-y text-sm">
                                {databases.map((item) => (
                                    <li
                                        key={item.slug}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <span className="flex items-center gap-2">
                                            <CircleAlert className="size-4 shrink-0" />
                                            <Link
                                                href={show(item.slug)}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {item.name}
                                            </Link>
                                            <span className="text-muted-foreground">
                                                {item.issue ===
                                                'missing_database'
                                                    ? t(
                                                          'console.health.issues.missing_database',
                                                      )
                                                    : t(
                                                          'console.health.issues.pending_migrations',
                                                          {
                                                              count:
                                                                  item.pendingMigrations ??
                                                                  0,
                                                          },
                                                      )}
                                            </span>
                                        </span>
                                        <PendingActionButton
                                            icon={DatabaseZap}
                                            label={t('console.health.migrate')}
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <section className="space-y-3">
                    <h3 className="font-medium">{t('console.health.tasks')}</h3>
                    <ConsoleTable
                        columns={taskColumns}
                        data={tasks}
                        emptyState={null}
                    />
                </section>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.health.queues')}
                    </h3>
                    <ConsoleTable
                        columns={queueColumns}
                        data={queues}
                        emptyState={null}
                    />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('console.health.backup')}</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-3 text-sm">
                        <Badge
                            variant={
                                backup.healthy ? 'secondary' : 'destructive'
                            }
                        >
                            {backup.healthy
                                ? t('console.health.healthy')
                                : t('console.health.unhealthy')}
                        </Badge>
                        <span>
                            {t('console.health.backup_last', {
                                date: formatDateTime(backup.lastAt, locale),
                            })}
                        </span>
                        <span className="text-muted-foreground">
                            {t('console.health.backup_size', {
                                size: String(backup.sizeMb),
                            })}
                        </span>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Health.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.health.title'),
            href: health(),
        },
    ],
});
