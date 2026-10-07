import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ConsoleTable } from '@/components/console/console-table';
import type { PaginationMeta } from '@/components/list-pagination';
import { OrganisationStatusBadge } from '@/components/console/organisation-status-badge';
import { QuotaUsage } from '@/components/console/quota-usage';
import Heading from '@/components/heading';
import { SupportGrantsCard } from '@/components/console/support-grants-card';
import { SampleBanner } from '@/components/sample-banner';
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
import { formatDate, formatRelative } from '@/lib/format-date';
import { index, show } from '@/routes/console/organisations';
import type {
    ConsoleOrganisationStatus,
    ConsoleOrganisationSummary,
    ConsoleSupportGrant,
    ConsoleSupportRequest,
    Translations,
} from '@/types';

type Props = {
    isSample: boolean;
    // La page affichee seulement : recherche, filtre et pagination se font cote serveur.
    organisations: ConsoleOrganisationSummary[];
    meta: PaginationMeta;
    filters: { search: string | null; status: string };
    // Reels, eux : les acces de support ouverts au compte connecte.
    supportGrants: ConsoleSupportGrant[];
    supportRequests: ConsoleSupportRequest[];
    // Null pour un profil editeur qui n'ouvre pas l'acces de support (Comptabilite).
    supportAvailable: boolean | null;
};

const AllStatuses = 'all';

const statuses: ConsoleOrganisationStatus[] = [
    'trial',
    'active',
    'past_due',
    'suspended',
    'deletion_scheduled',
    'deleted_by_owner',
];

/**
 * README ecran 27 : les organisations clientes, leur plan, leur etat et leur consommation. Des
 * metadonnees seulement, jamais le contenu d'une organisation (README section 3).
 */
export default function Organisations({
    isSample,
    organisations,
    meta,
    filters,
    supportGrants,
    supportRequests,
    supportAvailable,
}: Props) {
    const { t, locale } = useTranslation();
    const { search, setSearch, visit } = useServerList({
        url: index().url,
        filters,
    });
    const status = filters.status;
    const setStatus = (value: string) => visit({ filter: { status: value } });

    const columns: ColumnDef<ConsoleOrganisationSummary>[] = [
        {
            header: t('console.organisations.columns.name'),
            cell: ({ row }) => (
                <Link
                    href={show(row.original.slug)}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.original.name}
                </Link>
            ),
        },
        {
            header: t('console.organisations.columns.plan'),
            accessorKey: 'planName',
        },
        {
            header: t('console.organisations.columns.status'),
            cell: ({ row }) => (
                <OrganisationStatusBadge status={row.original.status} />
            ),
        },
        {
            header: t('console.organisations.columns.registrations'),
            cell: ({ row }) => (
                <QuotaUsage quota={row.original.usage.registrations} />
            ),
        },
        {
            header: t('console.organisations.columns.opened'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap">
                    {formatDate(row.original.openedAt, locale)}
                </span>
            ),
        },
        {
            header: t('console.organisations.columns.last_activity'),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap">
                    {row.original.lastActivityAt
                        ? formatRelative(row.original.lastActivityAt, locale)
                        : ''}
                </span>
            ),
        },
    ];

    const emptyState =
        meta.total === 0 &&
        (filters.search ?? '') === '' &&
        status === AllStatuses ? (
            <>
                <p className="font-medium">
                    {t('console.organisations.empty.title')}
                </p>
                <p className="text-muted-foreground text-sm">
                    {t('console.organisations.empty.description')}
                </p>
            </>
        ) : (
            <>
                <p className="font-medium">
                    {t('console.organisations.no_match.title')}
                </p>
                <p className="text-muted-foreground text-sm">
                    {t('console.organisations.no_match.description')}
                </p>
            </>
        );

    return (
        <>
            <Head title={t('console.organisations.title')} />

            <div className="flex flex-col space-y-6">
                {supportAvailable === null ? null : (
                    <SupportGrantsCard
                        available={supportAvailable}
                        grants={supportGrants}
                        requests={supportRequests}
                    />
                )}

                {isSample && <SampleBanner />}

                <Heading
                    variant="small"
                    title={t('console.organisations.title')}
                    description={t('console.organisations.description')}
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t(
                            'console.organisations.search_placeholder',
                        )}
                        aria-label={t(
                            'console.organisations.search_placeholder',
                        )}
                        className="max-w-xs"
                    />
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-60">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={AllStatuses}>
                                {t('console.organisations.filter_all')}
                            </SelectItem>
                            {statuses.map((item) => (
                                <SelectItem key={item} value={item}>
                                    {t(`console.statuses.${item}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <ConsoleTable
                    columns={columns}
                    data={organisations}
                    meta={meta}
                    onPageChange={(page) => visit({ page })}
                    emptyState={emptyState}
                />
            </div>
        </>
    );
}

Organisations.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.organisations.title'),
            href: index(),
        },
    ],
});
