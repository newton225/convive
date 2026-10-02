import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { CircleAlert, CircleCheck } from 'lucide-react';
import { ConsoleTable } from '@/components/console/console-table';
import { ExportLimitDialog } from '@/components/console/export-limit-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { security } from '@/routes/console';
import type {
    ConsoleAuditChains,
    ConsoleSecurityEvent,
    Translations,
} from '@/types';

type Props = {
    chains: ConsoleAuditChains;
    counts: { rateLimited: number; lockouts: number };
    events: ConsoleSecurityEvent[];
    exportsPerHour: number;
};

/**
 * L'ecran « Securite » de la console (README section 3) : l'integrite des journaux d'audit,
 * verifiee chaque nuit, puis les limites de debit atteintes et les connexions verrouillees. Ces
 * faits n'existaient qu'au journal du serveur.
 */
export default function ConsoleSecurity({
    chains,
    counts,
    events,
    exportsPerHour,
}: Props) {
    const { t, locale } = useTranslation();

    const columns: ColumnDef<ConsoleSecurityEvent>[] = [
        {
            header: t('console.security.columns.date'),
            cell: ({ row }) => formatDateTime(row.original.at, locale),
        },
        {
            header: t('console.security.columns.type'),
            cell: ({ row }) => (
                <Badge variant="outline">
                    {t(`console.security.types.${row.original.type}`)}
                </Badge>
            ),
        },
        {
            header: t('console.security.columns.subject'),
            cell: ({ row }) => (
                <span className="font-mono text-xs break-all">
                    {row.original.subject ?? ''}
                </span>
            ),
        },
        {
            header: t('console.security.columns.ip'),
            cell: ({ row }) => (
                <span className="font-mono text-xs">
                    {row.original.ip ?? ''}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('console.security.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('console.security.title')}
                    description={t('console.security.description')}
                />

                <Card data-test="console-export-limit">
                    <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle>
                                {t('console.security.export_limit.title')}
                            </CardTitle>
                            <p className="text-muted-foreground text-sm">
                                {t('console.security.export_limit.hint')}
                            </p>
                        </div>
                        <ExportLimitDialog exportsPerHour={exportsPerHour} />
                    </CardHeader>
                    <CardContent className="text-sm font-medium">
                        {t('console.security.export_limit.current', {
                            count: exportsPerHour,
                        })}
                    </CardContent>
                </Card>

                <Card data-test="console-audit-chains">
                    <CardHeader>
                        <CardTitle>{t('console.security.chains')}</CardTitle>
                        <p className="text-muted-foreground text-sm">
                            {t('console.security.chains_hint')}
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        {chains.checkedAt === null ? (
                            <p>{t('console.security.chains_never')}</p>
                        ) : chains.broken.length === 0 ? (
                            <p className="flex items-center gap-2">
                                <CircleCheck className="size-4" aria-hidden />
                                {t('console.security.chains_ok', {
                                    count: chains.count,
                                    date: formatDateTime(
                                        chains.checkedAt,
                                        locale,
                                    ),
                                })}
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {chains.broken.map((chain) => (
                                    <li
                                        key={chain.scope}
                                        className="flex gap-2"
                                    >
                                        <CircleAlert
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden
                                        />
                                        <span>
                                            <span className="font-medium">
                                                {chain.organisation ??
                                                    t(
                                                        'console.security.central_log',
                                                    )}
                                            </span>{' '}
                                            {t(
                                                'console.security.chain_broken',
                                                {
                                                    entry: chain.entryId,
                                                    date: formatDateTime(
                                                        chain.checkedAt,
                                                        locale,
                                                    ),
                                                },
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.security.events')}
                    </h3>
                    <p className="text-muted-foreground text-sm">
                        {t('console.security.events_day', {
                            limited: counts.rateLimited,
                            lockouts: counts.lockouts,
                        })}
                    </p>
                    <ConsoleTable
                        columns={columns}
                        data={events}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                {t('console.security.events_empty')}
                            </p>
                        }
                    />
                </section>
            </div>
        </>
    );
}

ConsoleSecurity.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.security.title'),
            href: security(),
        },
    ],
});
