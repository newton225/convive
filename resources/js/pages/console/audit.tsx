import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ConsoleTable } from '@/components/console/console-table';
import type { PaginationMeta } from '@/components/list-pagination';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useServerList } from '@/hooks/use-server-list';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { audit } from '@/routes/console';
import type {
    ConsoleAuditEntry,
    ConsoleAuditType,
    Translations,
} from '@/types';

type Props = {
    isSample: boolean;
    // La page affichee seulement : recherche, filtre et pagination se font cote serveur.
    entries: ConsoleAuditEntry[];
    meta: PaginationMeta;
    filters: { search: string | null; type: string };
};

const AllTypes = 'all';

const types: ConsoleAuditType[] = [
    'support_access_used',
    'trial_extended',
    'tenant_suspended',
    'tenant_reactivated',
    'deletion_scheduled',
    'plan_changed',
    'announcement_withdrawn',
    'operator_invited',
    'operator_removed',
    'plan_updated',
    'database_repaired',
    'deletion_cancelled',
    'tenant_erased',
    'payment_reminder_sent',
    'support_access_finished',
    'backup_run',
    'support_access_requested',
    'support_access_request_taken',
    'failed_job_retried',
    'failed_job_forgotten',
    'tenant_deleted_by_owner',
    'tenant_restored',
    'account_blocked',
    'account_unblocked',
    'two_factor_reset',
];

/**
 * README ecran 33 : le journal central. Actions de la console et operations sur les
 * organisations ; conservation 24 mois, en ecriture seule. Meme presentation que la
 * journalisation d'une organisation (ecran 23).
 */
export default function ConsoleAudit({
    isSample,
    entries,
    meta,
    filters,
}: Props) {
    const { t, locale } = useTranslation();
    const { search, setSearch, visit } = useServerList({
        url: audit().url,
        filters,
    });
    const type = filters.type;
    const setType = (value: string) => visit({ filter: { type: value } });

    const actorLabel = (entry: ConsoleAuditEntry) =>
        entry.actor ?? t('console.system_actor');

    const columns: ColumnDef<ConsoleAuditEntry>[] = [
        {
            header: t('console.audit.columns.date'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap">
                    {formatDateTime(row.original.at, locale)}
                </span>
            ),
        },
        {
            header: t('console.audit.columns.type'),
            cell: ({ row }) => (
                <Badge variant="secondary">
                    {t(`console.audit.types.${row.original.type}`)}
                </Badge>
            ),
        },
        {
            header: t('console.audit.columns.message'),
            cell: ({ row }) =>
                t(`console.audit.messages.${row.original.type}`, {
                    actor: actorLabel(row.original),
                    organisation: row.original.organisation ?? '',
                }),
        },
        {
            header: t('console.audit.columns.actor'),
            cell: ({ row }) => actorLabel(row.original),
        },
        {
            header: t('console.audit.columns.ip'),
            cell: ({ row }) => (
                <span className="font-mono text-xs">
                    {row.original.ip ?? ''}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('console.audit.title')} />

            <div className="flex flex-col space-y-6">
                {isSample && <SampleBanner />}

                <Heading
                    variant="small"
                    title={t('console.audit.title')}
                    description={t('console.audit.description')}
                />

                <p className="text-muted-foreground text-sm">
                    {t('console.audit.retention')}
                </p>

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('console.audit.search_placeholder')}
                        aria-label={t('console.audit.search_placeholder')}
                        className="max-w-xs"
                    />
                    <Select value={type} onValueChange={setType}>
                        <SelectTrigger className="w-60">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={AllTypes}>
                                {t('console.audit.filter_all')}
                            </SelectItem>
                            {types.map((item) => (
                                <SelectItem key={item} value={item}>
                                    {t(`console.audit.types.${item}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <ConsoleTable
                    columns={columns}
                    data={entries}
                    meta={meta}
                    onPageChange={(page) => visit({ page })}
                    emptyState={
                        meta.total === 0 &&
                        (filters.search ?? '') === '' &&
                        type === AllTypes ? (
                            <>
                                <p className="font-medium">
                                    {t('console.audit.empty.title')}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {t('console.audit.empty.description')}
                                </p>
                            </>
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                {t('console.audit.no_match')}
                            </p>
                        )
                    }
                />
            </div>
        </>
    );
}

ConsoleAudit.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.audit.title'),
            href: audit(),
        },
    ],
});
